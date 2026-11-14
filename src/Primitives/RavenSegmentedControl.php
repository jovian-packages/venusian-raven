<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Toolkits\Primitives\TKSegmentedControl;
use WeakReference;

/**
 * Raven's segmented control is the bar of its tab view: on macOS, GNOME and the glass themes the
 * tabs are drawn as one (a capsule of titles, the selected one on a pill that slides). So this is
 * a tab view whose pages are empty: it measures as its bar alone, and Raven draws every segment.
 */
class RavenSegmentedControl extends TKSegmentedControl implements RavenNative
{
    use RavenPrimitive;

    /** @var list<int> the empty page of each segment, by index */
    protected array $pages = [];

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, array $segments, int $selected)
    {
        parent::__construct($name, $window, $parent, $placement, $segments, $selected);
        $tabs = $this->adoptNative(Rwidget_create(R_WIDGET_TAB_VIEW));
        $self = WeakReference::create($this);
        // a click on a segment; Rtab_view_set_active() does not call it
        Rtab_view_set_changed_callback($tabs, static function (int $tabs, int $index) use ($self): void {
            $primitive = $self->get();
            if (! $primitive?->handling() || $index < 0 || $primitive->selected === $index) {
                return;
            }
            $primitive->nativeSelected($index);
            $primitive->session()->post(new SelectionChanged($primitive->window->name(), $primitive->path(), $primitive->uuid, $index, $primitive->segments[$index] ?? null));
        }, null);
        $this->applySegments($this->segments);
    }

    protected function applySegments(array $segments): void
    {
        Rwidget_clear_children($this->control);
        $this->pages = [];
        foreach (array_values($segments) as $label) {
            $page = Rwidget_create(R_WIDGET_VBOX);
            Rtab_view_add_tab($this->control, $label, $page);
            $this->pages[] = $page;
        }
        $this->applySelected($this->selected);
    }

    protected function applySegment(int $index, string $label): void
    {
        Rtab_view_set_tab_title($this->control, $this->pages[$index], $label);
    }

    protected function applySelected(int $index): void
    {
        Rtab_view_set_active($this->control, $index);
    }
}
