<?php

namespace Jovian\Toolkits\Raven\UI\Concerns;

use Surface\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\Insets;

/**
 * What a Raven column and row share: a vertical or horizontal box (the control) inside a
 * border (the native). Spacing is the box's; padding is the border's, so it sits inside the
 * background. A child's fill (its share of the box's axis) and alignment (its place inside
 * its slot) are its slot in the box, placed here whenever they change. Children are
 * appended, taken out and moved in the box.
 *
 * Homogeneous makes every slot a fill slot of coefficient 1, which Raven shares by
 * coefficient alone. The box still asks its parent for the sum of its children's lengths,
 * not the largest times their number: given no more than that, the longest child gets less
 * than it asks for (a wrapping label wraps sooner).
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
        $this->applyPadding($this->padding());
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
     * The border's padding, each side its own, inside the background.
     */
    protected function applyPadding(Insets $padding): void
    {
        Rborder_set_padding($this->native, $padding->left, $padding->top, $padding->right, $padding->bottom);
    }

    /**
     * The child's slot from what fill() and align() set: its share of the box's axis and its
     * place inside the slot. Runs before the child's own part (and on minSize(), where it
     * changes nothing).
     */
    protected function childLayoutChanged(TKPrimitive $child): void
    {
        $this->isHomogeneous()
            ? Rslot_set_fill($child->native(), 1.0)
            : self::placeShare($child->native(), $this->vertical(), [$child->expands(true), $child->expands(false)]);
        self::placeAlignment($child->native(), $child->slotAligned());
        $this->tellExpansion();
    }

    /**
     * Every child a fill slot of the same coefficient: Raven's box gives fill slots the room
     * left by the auto slots, the gaps and the margins (slot padding) in proportion to the
     * coefficient alone, whatever each one's desired size, so every child is as long as the
     * next. Off, each slot is its fill share again.
     */
    protected function applyHomogeneous(bool $on): void
    {
        foreach ($this->children() as $child) {
            $this->childLayoutChanged($child);
        }
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
