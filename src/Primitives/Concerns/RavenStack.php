<?php

namespace Jovian\Toolkits\Raven\Primitives\Concerns;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive as Child;

/**
 * Column and row: a Raven VBox or HBox inside the primitive's border, children in display
 * order. A child that fills the stack's axis takes a share of the spare space (fill 1); the
 * others keep their desired size from the start. Across the axis a child fills, or sits where
 * its align() puts it.
 */
trait RavenStack
{
    protected bool $vertical;

    protected function buildStack(bool $vertical): void
    {
        $this->vertical = $vertical;
        $this->adoptNative(Rwidget_create($vertical ? R_WIDGET_VBOX : R_WIDGET_HBOX));
        Rpanel_set_spacing($this->control, $this->spacing);
        Rborder_set_padding($this->native, $this->padding, $this->padding, $this->padding, $this->padding);
    }

    public function placeChild(Child&RavenNative $child): void
    {
        $native = $child->native();
        [$horizontal, $vertical] = $child->fills();
        [$halign, $valign] = $child->ravenAlign();
        if ($this->vertical) {
            Rslot_set_halign($native, $horizontal ? R_HALIGN_FILL : $halign);
            $vertical ? Rslot_set_fill($native, 1.0) : Rslot_set_auto($native);
            if (! $vertical) {
                Rslot_set_valign($native, $valign);
            }
        } else {
            Rslot_set_valign($native, $vertical ? R_VALIGN_FILL : $valign);
            $horizontal ? Rslot_set_fill($native, 1.0) : Rslot_set_auto($native);
            if (! $horizontal) {
                Rslot_set_halign($native, $halign);
            }
        }
    }

    protected function insertNative(Child $child): void
    {
        Rwidget_add_child($this->control, self::nativeOf($child));
        if ($child instanceof RavenNative) {
            $this->placeChild($child);
        }
    }

    protected function applySpacing(int $spacing): void
    {
        Rpanel_set_spacing($this->control, $spacing);
    }

    /** Detached and put back at $index: Rwidget_remove_child keeps the widget. */
    protected function applyOrder(Child $child, int $index): void
    {
        $native = self::nativeOf($child);
        Rwidget_remove_child($this->control, $native);
        Rwidget_insert_child($this->control, $native, $index);
        if ($child instanceof RavenNative) {
            $this->placeChild($child);
        }
    }
}
