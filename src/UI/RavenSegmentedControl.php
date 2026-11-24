<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKSegmentedControl;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven tab view with empty pages: Raven has no segmented control.
 */
class RavenSegmentedControl extends TKSegmentedControl
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /** @var list<int> each segment's empty page */
    private array $pages = [];

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_TAB_VIEW));
        $this->write(fn () => $this->applySegments($this->segments(), $this->selected()));

        $self = WeakReference::create($this);
        Rtab_view_set_changed_callback($this->control, static function (int $tabs, int $index) use ($self): void {
            $self->get()?->chosen($index);
        }, null);
    }

    protected function applySegments(array $segments, int $selected): void
    {
        Rwidget_clear_children($this->control);
        $this->pages = [];
        foreach ($segments as $label) {
            $page = Rwidget_create(R_WIDGET_VBOX);
            Rtab_view_add_tab($this->control, $label, $page);
            $this->pages[] = $page;
        }

        if ($selected >= 0) {
            $this->applySelected($selected);
        }
    }

    protected function applySegment(int $index, string $label): void
    {
        Rtab_view_set_tab_title($this->control, $this->pages[$index], $label);
    }

    protected function applySelected(int $index): void
    {
        Rtab_view_set_active($this->control, $index);
    }

    /** The callback goes first: ext-raven would otherwise hold it until it next sweeps. */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rtab_view_set_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
