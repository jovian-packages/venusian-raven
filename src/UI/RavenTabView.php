<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKTabView;
use Surface\Toolkits\UI\TKPrimitive;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven tab view: each tab is a child with a title on its slot.
 */
class RavenTabView extends TKTabView
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_TAB_VIEW));

        $self = WeakReference::create($this);
        Rtab_view_set_changed_callback($this->control, static function (int $tabs, int $index) use ($self): void {
            $self->get()?->chosen($index);
        }, null);
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rtab_view_add_tab($this->control, $this->title($child->name()), $child->native());
        $this->tellExpansion();
    }

    protected function detachNative(TKPrimitive $child): void
    {
        if (Rwidget_is_valid($this->control) === R_TRUE && Rwidget_is_valid($child->native()) === R_TRUE) {
            Rwidget_remove_child($this->control, $child->native());
        }
        $this->tellExpansion();
    }

    protected function reorderNative(TKPrimitive $child, int $index): void
    {
        Rwidget_insert_child($this->control, $child->native(), $index);
        Rslot_set_title($child->native(), $this->title($child->name()));
    }

    protected function applyTitle(TKPrimitive $page, string $title): void
    {
        Rtab_view_set_tab_title($this->control, $page->native(), $title);
    }

    protected function applySelected(TKPrimitive $page): void
    {
        Rtab_view_show_tab($this->control, $page->native());
    }

    protected function childLayoutChanged(TKPrimitive $child): void
    {
        $this->tellExpansion();
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rtab_view_set_changed_callback($this->control, null, null);
        }
        $this->releaseWidget();
    }
}
