<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\TKFixed;
use Surface\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TKPrimitive as ChildContract;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven canvas in a border: each child at its frame's position (Rslot_set_position, from
 * the canvas's top-left) and sized to its frame (Rslot_set_size), or to its minimum size when
 * that is larger. A canvas asks its parent for no room of its own, so the border's minimum
 * size covers every child, or what minSize() asked when that is larger.
 */
class RavenFixed extends TKFixed
{
    use RavenNative {
        applyMinSize as private nativeMinSize;
    }

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_CANVAS));
    }

    /**
     * At the frame it was created at.
     */
    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
        $this->frameChild($child);
    }

    /**
     * Out of the canvas; the child destroys its native right after. The rest still covered.
     */
    protected function detachNative(TKPrimitive $child): void
    {
        $native = $child->native();
        if (Rwidget_is_valid($this->control) === R_TRUE && Rwidget_is_valid($native) === R_TRUE && Rwidget_get_parent($native) === $this->control) {
            Rwidget_remove_child($this->control, $native);
        }
        $this->cover();
    }

    protected function applyFrame(ChildContract $child, Placement $frame): void
    {
        $this->frameChild($child);
    }

    /**
     * A child's minimum size may now be larger or smaller than its frame. Its fill and
     * alignment have no part here: a framed child is placed by its frame.
     */
    protected function childLayoutChanged(TKPrimitive $child): void
    {
        $this->frameChild($child);
    }

    /**
     * Never reached: only a column or row lets a child move (TKPrimitive::moveTo refuses here).
     *
     * @throws ToolkitException
     */
    protected function reorderNative(TKPrimitive $child, int $index): void
    {
        throw new ToolkitException("'{$this->path()}' keeps no order: its children are placed by frame.");
    }

    /**
     * Put $child's native at its frame now, no smaller than its minimum size, and cover it.
     */
    private function frameChild(TKPrimitive $child): void
    {
        $frame = $this->frameOf($child);
        [$width, $height] = $child->minimumSize();

        Rslot_set_position($child->native(), $frame->x, $frame->y);
        Rslot_set_size($child->native(), max($width, $frame->width), max($height, $frame->height));
        $this->cover();
    }

    /**
     * The floor is what covers the children, or what was asked when that is larger.
     */
    protected function applyMinSize(int $width, int $height): void
    {
        $this->cover();
    }

    /**
     * The border's minimum size: the far edge of every child, at least the asked minimum.
     */
    private function cover(): void
    {
        [$width, $height] = $this->minimumSize();

        foreach ($this->children() as $child) {
            $frame = $this->frameOf($child);
            [$minWidth, $minHeight] = $child->minimumSize();
            $width = max($width, $frame->x + max($frame->width, $minWidth));
            $height = max($height, $frame->y + max($frame->height, $minHeight));
        }

        $this->nativeMinSize($width, $height);
    }
}
