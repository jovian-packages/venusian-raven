<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKFlow;
use Surface\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\Insets;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * Raven's wrap box (R_WIDGET_WRAP_BOX) in a border, whose padding is the box's. The box puts
 * each child at its own size (slot padding, the child's margin, included), from the left,
 * and goes on to the next line where the width is used up; a collapsed child takes no room.
 * Spacing is the box's (Rpanel_set_spacing): the gap on a line and between lines. The box
 * remembers the width it was given and asks for one more layout pass when it changes, so its
 * height follows its lines. A child's fill and alignment have nothing to place: its slot is
 * its own size.
 */
class RavenFlow extends TKFlow
{
    use RavenNative;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_WRAP_BOX));
        Rpanel_set_spacing($this->control, $this->spacing());
        $this->applyPadding($this->padding());
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
    }

    /**
     * Out of the box; the child destroys its native right after. Rwidget_remove_child keeps
     * the widget alive.
     */
    protected function detachNative(TKPrimitive $child): void
    {
        $native = $child->native();
        if (Rwidget_is_valid($this->control) === R_TRUE && Rwidget_is_valid($native) === R_TRUE && Rwidget_get_parent($native) === $this->control) {
            Rwidget_remove_child($this->control, $native);
        }
    }

    /**
     * Where it flows: Raven places children in their order. Rwidget_insert_child takes a child
     * already in the box out first and $index counts the others, as TKGroup::arrange() does.
     * Taking it out drops the window's focus when the focus is inside it, so the focus goes
     * back once it is in its new place.
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

    protected function applySpacing(int $spacing): void
    {
        Rpanel_set_spacing($this->control, $spacing);
    }

    /**
     * The border's padding, each side its own, inside the background.
     */
    protected function applyPadding(Insets $padding): void
    {
        Rborder_set_padding($this->native, $padding->left, $padding->top, $padding->right, $padding->bottom);
    }
}
