<?php

namespace Jovian\Toolkits\Raven\UI;

use RShaderLook;
use Surface\Toolkits\UI\TKImage;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Corners;
use Surface\Contracts\Toolkits\UI\ImageScaling;
use Jovian\Toolkits\Raven\Raven;
use Jovian\Toolkits\Raven\UI\Support\RavenFrames;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven image inside a border, showing a texture that is this primitive's own.
 *
 * A fitted picture's height follows the width it is given. Raven measures a widget before it
 * knows its width (an image asks for its texture's size), so once per frame, before the
 * layout (RavenFrames), the image reads the width the last layout gave it and fixes its
 * height to the picture's ratio (Rwidget_set_size): a 3709x2770 picture asked 2770 high
 * whatever its width and was drawn small in the middle of that.
 *
 * Its corners: Raven's image rounds with one radius (Rimage_set_corner_radius), and Raven
 * clips only to rectangles. When the corners differ, the picture is drawn instead as the
 * border's background, by an effect (resources/shaders/picture.spv) inside the border's own
 * four radii, the image widget kept for its size and drawn at opacity 0. Effects run on
 * Raven's Vulkan backend only; elsewhere the picture stays square there.
 */
class RavenImage extends TKImage
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /** R_INVALID_ID for none: Raven refuses 0 as a texture, and would keep the picture. */
    private int $texture = R_INVALID_ID;

    /** The texture's size in pixels, [0, 0] for none. */
    private array $texture_size = [0, 0];

    /** The border's width and height the last refit saw, [-1, -1] before any. */
    private array $seen = [-1.0, -1.0];

    /** The picture is drawn by the border's effect (corners that differ). */
    private bool $by_effect = false;

    private static ?int $picture_shader = null;

    protected function bootstrapNative(): void
    {
        $file = $this->file();
        $this->texture = is_null($file) ? R_INVALID_ID : self::read($file);
        $this->texture_size = $this->sizeOf($this->texture);

        $this->adoptNative(Rwidget_create(R_WIDGET_IMAGE));
        Rimage_set_texture($this->control, $this->texture);
        $this->applyScaling($this->scaling());

        RavenFrames::watch($this, function (): void {
            $this->refit();
        });
    }

    protected function applyFile(?string $file): void
    {
        $texture = is_null($file) ? R_INVALID_ID : self::read($file);
        Rimage_set_texture($this->control, $texture);
        $this->dropTexture();
        $this->texture = $texture;
        $this->texture_size = $this->sizeOf($texture);
        $this->refresh();
    }

    protected function applyScaling(ImageScaling $scaling): void
    {
        Rimage_set_stretch($this->control, match ($scaling) {
            ImageScaling::FIT => R_STRETCH_FIT,
            ImageScaling::FILL => R_STRETCH_COVER,
            ImageScaling::CENTER => R_STRETCH_NONE,
            ImageScaling::STRETCH => R_STRETCH_FILL,
        });
        $this->refresh();
    }

    /** Rounds the picture too. */
    protected function applyCornerRadius(float $radius): void
    {
        Rborder_set_corner_radius($this->native, $radius, $radius, $radius, $radius);
        $this->roundPicture(Corners::of($radius));
    }

    /**
     * The border takes each corner's own radius; the picture rounds with the one radius when
     * the corners agree, and is drawn by the border's effect when they differ.
     */
    protected function applyCorners(Corners $corners): void
    {
        Rborder_set_corner_radius($this->native, $corners->topLeft, $corners->topRight, $corners->bottomRight, $corners->bottomLeft);
        $this->roundPicture($corners);
    }

    protected function releaseNative(): void
    {
        RavenFrames::forget($this);
        if ($this->by_effect) {
            Rborder_set_effect($this->native, R_INVALID_ID, null);
        }
        $this->releaseWidget();
        $this->dropTexture();
    }

    private function roundPicture(Corners $corners): void
    {
        $this->by_effect = ! $corners->isUniform() && Raven::drawsEffects() && ! is_null(self::pictureShader());
        Rimage_set_corner_radius($this->control, $corners->isUniform() ? $corners->topLeft : 0.0);
        Rwidget_set_opacity($this->control, $this->by_effect ? 0.0 : 1.0);
        if (! $this->by_effect) {
            Rborder_set_effect($this->native, R_INVALID_ID, null);
        }
        $this->refresh();
    }

    /**
     * What a refit derives from the size: fitted again on the next frame.
     */
    private function refresh(): void
    {
        $this->seen = [-1.0, -1.0];
    }

    /**
     * Once per frame, before the layout: the size the last layout gave the image decides a
     * fitted picture's height and what part of the texture the effect shows.
     */
    private function refit(): void
    {
        if (is_null($this->native) || is_null($this->control)) {
            return;
        }

        Rwidget_get_rect($this->native, $x, $y, $width, $height);
        if (abs($width - $this->seen[0]) < 0.5 && abs($height - $this->seen[1]) < 0.5) {
            return;
        }
        $this->seen = [$width, $height];

        [$tw, $th] = $this->texture_size;
        $fits = $this->scaling() === ImageScaling::FIT && $tw > 0 && $th > 0;
        Rwidget_set_size($this->control, -1.0, $fits && $width > 0.0 ? round($width * $th / $tw, 2) : -1.0);

        if ($this->by_effect && $width > 0.0 && $height > 0.0 && $tw > 0 && $th > 0) {
            $look = new RShaderLook();
            Rshader_look_init($look);
            $look->color = [1.0, 1.0, 1.0, 1.0];
            $look->params = $this->region($width, $height, $tw, $th);
            $look->texture = $this->texture;
            $look->animate = R_FALSE;
            $look->pointer = R_FALSE;
            Rborder_set_effect($this->native, self::pictureShader(), $look);
        }
    }

    /**
     * The texture coordinates at the rectangle's corners, as the image's scaling places the
     * picture: [u0, v0, u1, v1]; past 0..1 where the picture does not reach.
     *
     * @return array{float, float, float, float}
     */
    private function region(float $width, float $height, int $tw, int $th): array
    {
        $scale = match ($this->scaling()) {
            ImageScaling::STRETCH => null,
            ImageScaling::FILL => max($width / $tw, $height / $th),
            ImageScaling::FIT => min($width / $tw, $height / $th),
            ImageScaling::CENTER => 1.0,
        };
        if (is_null($scale)) {
            return [0.0, 0.0, 1.0, 1.0];
        }

        $shown_w = $tw * $scale;
        $shown_h = $th * $scale;
        $left = ($width - $shown_w) / 2;
        $top = ($height - $shown_h) / 2;

        return [-$left / $shown_w, -$top / $shown_h, ($width - $left) / $shown_w, ($height - $top) / $shown_h];
    }

    /**
     * @return array{int, int}
     */
    private function sizeOf(int $texture): array
    {
        if ($texture === R_INVALID_ID) {
            return [0, 0];
        }
        Rtexture_get_size($texture, $width, $height);

        return [(int) $width, (int) $height];
    }

    private static function pictureShader(): ?int
    {
        if (is_null(self::$picture_shader) || Rshader_is_valid(self::$picture_shader) !== R_TRUE) {
            $shader = Rshader_create_from_file(dirname(__DIR__, 2).'/resources/shaders/picture.spv', 0);
            self::$picture_shader = Rshader_is_valid($shader) === R_TRUE ? $shader : null;
        }

        return self::$picture_shader;
    }

    private function dropTexture(): void
    {
        if ($this->texture !== R_INVALID_ID && Rtexture_is_valid($this->texture) === R_TRUE) {
            Rtexture_destroy($this->texture);
        }
        $this->texture = R_INVALID_ID;
    }

    /**
     * @throws ToolkitException When Raven reads no picture in it.
     */
    private static function read(string $file): int
    {
        $texture = Rtexture_create_from_file($file);

        return Rtexture_is_valid($texture) === R_TRUE ? $texture : throw new ToolkitException("'{$file}' is not an image Raven reads.");
    }
}
