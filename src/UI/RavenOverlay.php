<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKOverlay;
use Surface\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\Insets;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * Raven's overlay (R_WIDGET_OVERLAY) in a border, whose padding is the overlay's. Raven draws
 * its children in order, the first at the back, and places each in the whole area by its
 * slot: its alignment, and its margin as slot padding. Filling an axis is FILL there,
 * whatever the alignment; on an axis it does not fill, the alignment places it.
 *
 * Raven's overlay asks for the largest of its children. A child that fills both ways must
 * not size it, so its native's desired size is capped at 0 (Rwidget_set_max_size): Raven still
 * stretches a FILL slot over the whole area, so it takes the overlay's size and gives it none.
 */
class RavenOverlay extends TKOverlay
{
    use RavenNative;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_OVERLAY));
        $this->applyPadding($this->padding());
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
        $this->childLayoutChanged($child);
    }

    /**
     * Out of the overlay; the child destroys its native right after.
     */
    protected function detachNative(TKPrimitive $child): void
    {
        $native = $child->native();
        if (Rwidget_is_valid($this->control) === R_TRUE && Rwidget_is_valid($native) === R_TRUE && Rwidget_get_parent($native) === $this->control) {
            Rwidget_remove_child($this->control, $native);
        }
        $this->tellExpansion();
    }

    /**
     * Where it is drawn: Raven paints children in their order, so the index is the depth.
     * Rwidget_insert_child takes the child out first, which drops the window's focus when it
     * is inside: the focus goes back once the child is in its new place.
     */
    protected function reorderNative(TKPrimitive $child, int $index): void
    {
        $native = $child->native();
        $window = Rwidget_get_window($this->control);
        $focused = $window === R_INVALID_ID ? R_INVALID_ID : Rwindow_get_focused_widget($window);
        $inside = false;
        for ($at = $focused; $at !== R_INVALID_ID && $at !== 0; $at = Rwidget_get_parent($at)) {
            if ($at === $native) {
                $inside = true;
                break;
            }
        }

        Rwidget_insert_child($this->control, $native, $index);

        if ($inside && Rwidget_is_valid($focused) === R_TRUE) {
            Rwidget_focus($focused);
        }
    }

    /**
     * The border's padding, each side its own, inside the background.
     */
    protected function applyPadding(Insets $padding): void
    {
        Rborder_set_padding($this->native, $padding->left, $padding->top, $padding->right, $padding->bottom);
    }

    /**
     * The child's slot from fill() and align(), and whether its desired size counts.
     */
    protected function childLayoutChanged(TKPrimitive $child): void
    {
        [$fillsH, $fillsV] = $child->fills();
        [$alignH, $alignV] = $child->aligned();
        self::placeAlignment($child->native(), [$fillsH ? Align::FILL : $alignH, $fillsV ? Align::FILL : $alignV]);

        $this->sizesOverlay($child)
            ? Rwidget_set_max_size($child->native(), -1, -1)
            : Rwidget_set_max_size($child->native(), 0, 0);
        $this->tellExpansion();
    }

    protected function childVisibilityChanged(TKPrimitive $child): void
    {
        $this->tellExpansion();
    }
}
