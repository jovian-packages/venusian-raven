<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Surface\Contracts\Toolkits\Primitives\ImageScaling;
use Surface\Toolkits\Primitives\TKImage;

/** A Raven image showing a texture decoded from the file (PNG, JPEG, BMP, TGA, GIF, PSD, SVG). */
class RavenImage extends TKImage implements RavenNative
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
    }

    protected int $texture = 0;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, ?string $file)
    {
        parent::__construct($name, $window, $parent, $placement, $file);
        $this->adoptNative(Rwidget_create(R_WIDGET_IMAGE));
        $this->applyScaling($this->scaling);
        $this->applyFile($file);
    }

    /** A file Raven cannot read shows nothing, as the other toolkits do. */
    protected function applyFile(?string $file): void
    {
        $old = $this->texture;
        $this->texture = 0;
        if (! is_null($file) && is_file($file)) {
            $texture = Rtexture_create_from_file($file);
            $this->texture = Rtexture_is_valid($texture) === R_TRUE ? $texture : 0;
        }
        Rimage_set_texture($this->control, $this->texture);
        if ($old !== 0) {
            Rtexture_destroy($old);
        }
    }

    protected function applyScaling(ImageScaling $scaling): void
    {
        Rimage_set_stretch($this->control, match ($scaling) {
            ImageScaling::FIT => R_STRETCH_FIT,
            ImageScaling::FILL => R_STRETCH_COVER,
            ImageScaling::STRETCH => R_STRETCH_FILL,
            ImageScaling::CENTER => R_STRETCH_NONE,
        });
    }

    protected function destroyNative(): void
    {
        $this->destroyPrimitive();
        if ($this->texture !== 0) {
            Rtexture_destroy($this->texture);
            $this->texture = 0;
        }
    }
}
