<?php

namespace Jovian\Toolkits\Raven\UI\Concerns;

use Surface\Toolkits\UI\TKPrimitive;

/**
 * What a Raven column and row share: a vertical or horizontal box (the control) inside a
 * border (the native). Spacing is the box's; padding is the border's, so it sits inside the
 * background. A child's fill (its share of the box's axis) and alignment (its place inside
 * its slot) are its slot in the box, placed here whenever they change. Children are
 * appended, taken out and moved in the box.
 */
trait RavenBoxChildren
{
    use RavenNative;

    /**
     * Whether the box stacks its children top to bottom.
     */
    abstract protected function vertical(): bool;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create($this->vertical() ? R_WIDGET_VBOX : R_WIDGET_HBOX));
        Rpanel_set_spacing($this->control, $this->spacing());
        Rborder_set_padding($this->native, $this->padding, $this->padding, $this->padding, $this->padding);
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
        $this->childLayoutChanged($child);
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
        $this->tellExpansion();
    }

    /**
     * Rwidget_insert_child moves a child already in the box: it is taken out first and $index
     * counts the others, as TKGroup::arrange() does. Taking it out drops the window's focus
     * when the focus is inside it, so the focus goes back once it is in its new place. The
     * slot goes with the child.
     */
    protected function reorderNative(TKPrimitive $child, int $index): void
    {
        $native = $child->native();
        $window = Rwidget_get_window($this->control);
        $focused = $window === R_INVALID_ID ? R_INVALID_ID : Rwindow_get_focused_widget($window);
        $inside = $focused !== R_INVALID_ID && self::within($focused, $native);

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
     * The child's slot from what fill() and align() set: its share of the box's axis and its
     * place inside the slot. Runs before the child's own part (and on minSize(), where it
     * changes nothing).
     */
    protected function childLayoutChanged(TKPrimitive $child): void
    {
        self::placeShare($child->native(), $this->vertical(), [$child->expands(true), $child->expands(false)]);
        self::placeAlignment($child->native(), $child->aligned());
        $this->tellExpansion();
    }

    protected function childVisibilityChanged(TKPrimitive $child): void
    {
        $this->tellExpansion();
    }

    /**
     * Whether $widget is $ancestor or inside it.
     */
    private static function within(int $widget, int $ancestor): bool
    {
        for ($at = $widget; $at !== R_INVALID_ID && $at !== 0; $at = Rwidget_get_parent($at)) {
            if ($at === $ancestor) {
                return true;
            }
        }

        return false;
    }
}
