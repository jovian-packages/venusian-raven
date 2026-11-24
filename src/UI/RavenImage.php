<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKImage;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\ImageScaling;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven image inside a border, showing a texture that is this primitive's own.
 */
class RavenImage extends TKImage
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /** R_INVALID_ID for none: Raven refuses 0 as a texture, and would keep the picture. */
    private int $texture = R_INVALID_ID;

    protected function bootstrapNative(): void
    {
        $file = $this->file();
        $this->texture = is_null($file) ? R_INVALID_ID : self::read($file);

        $this->adoptNative(Rwidget_create(R_WIDGET_IMAGE));
        Rimage_set_texture($this->control, $this->texture);
        $this->applyScaling($this->scaling());
    }

    protected function applyFile(?string $file): void
    {
        $texture = is_null($file) ? R_INVALID_ID : self::read($file);
        Rimage_set_texture($this->control, $texture);
        $this->dropTexture();
        $this->texture = $texture;
    }

    protected function applyScaling(ImageScaling $scaling): void
    {
        Rimage_set_stretch($this->control, match ($scaling) {
            ImageScaling::FIT => R_STRETCH_FIT,
            ImageScaling::FILL => R_STRETCH_COVER,
            ImageScaling::CENTER => R_STRETCH_NONE,
            ImageScaling::STRETCH => R_STRETCH_FILL,
        });
    }

    /** Rounds the picture too. */
    protected function applyCornerRadius(float $radius): void
    {
        Rborder_set_corner_radius($this->native, $radius, $radius, $radius, $radius);
        Rimage_set_corner_radius($this->control, $radius);
    }

    protected function releaseNative(): void
    {
        $this->releaseWidget();
        $this->dropTexture();
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
