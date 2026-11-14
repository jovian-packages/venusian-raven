<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenContainer;
use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive as Child;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Toolkits\Primitives\TKFixed;

/** A Raven canvas panel: each child at its frame. Its minimum size covers every frame. */
class RavenFixed extends TKFixed implements RavenContainer
{
    use RavenPrimitive;

    protected array $asked_minimum = [0, 0];

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement)
    {
        parent::__construct($name, $window, $parent, $placement);
        $this->adoptNative(Rwidget_create(R_WIDGET_CANVAS));
    }

    /** A framed child ignores fill and align. */
    public function placeChild(Child&RavenNative $child): void {}

    protected function insertNative(Child $child): void
    {
        $native = self::nativeOf($child);
        $frame = $this->frameOf($child);
        Rwidget_add_child($this->control, $native);
        Rslot_set_position($native, $frame->x, $frame->y);
        Rslot_set_size($native, $frame->width, $frame->height);
        $this->coverFrames();
    }

    public function forgetChild(Child $child): void
    {
        parent::forgetChild($child);
        $this->coverFrames();
    }

    protected function applyMove(Child $child, int $x, int $y): void
    {
        Rslot_set_position(self::nativeOf($child), $x, $y);
        $this->coverFrames();
    }

    protected function applyResize(Child $child, int $width, int $height): void
    {
        Rslot_set_size(self::nativeOf($child), $width, $height);
        $this->coverFrames();
    }

    protected function applyMinSize(int $width, int $height): void
    {
        $this->asked_minimum = [$width, $height];
        $this->coverFrames();
    }

    protected function coverFrames(): void
    {
        [$width, $height] = $this->asked_minimum;
        foreach ($this->frames as $frame) {
            $width = max($width, $frame->x + $frame->width);
            $height = max($height, $frame->y + $frame->height);
        }
        Rwidget_set_min_size($this->native, $width, $height);
    }
}
