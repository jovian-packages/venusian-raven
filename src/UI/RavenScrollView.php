<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKPrimitive;
use Surface\Toolkits\UI\TKScrollView;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven scroll box. Its child is as long as it asks to be along the way it scrolls.
 */
class RavenScrollView extends TKScrollView
{
    use RavenNative;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_SCROLL_BOX));
        $this->applyScrollbars($this->scrollsAcross(), $this->scrollsDown());
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
        self::placeAlignment($child->native(), $child->aligned());
        $this->tellExpansion();
    }

    protected function detachNative(TKPrimitive $child): void
    {
        if (Rwidget_is_valid($this->control) === R_TRUE && Rwidget_is_valid($child->native()) === R_TRUE) {
            Rwidget_remove_child($this->control, $child->native());
        }
        $this->tellExpansion();
    }

    protected function applyScrollbars(bool $horizontal, bool $vertical): void
    {
        Rscroll_box_set_scroll_both($this->control, $horizontal && $vertical ? R_TRUE : R_FALSE);
        Rscroll_box_set_orientation($this->control, $vertical ? R_VERTICAL : R_HORIZONTAL);
    }

    protected function childLayoutChanged(TKPrimitive $child): void
    {
        self::placeAlignment($child->native(), $child->aligned());
        $this->tellExpansion();
    }

    protected function childVisibilityChanged(TKPrimitive $child): void
    {
        $this->tellExpansion();
    }
}
