<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKPrimitive;
use Surface\Toolkits\UI\TKSplitView;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven splitter: a pane's share is its slot's fill. It has no callback, so a timer
 * compares while the mouse is down.
 */
class RavenSplitView extends TKSplitView
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    private int $watching = 0;

    private bool $was_down = false;

    private float $length = 0.0;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_SPLITTER));
        Rsplitter_set_orientation($this->control, $this->isHorizontal() ? R_HORIZONTAL : R_VERTICAL);

        $self = WeakReference::create($this);
        $this->watching = Rtimer_create(1 / 30, R_TRUE, static function (int $timer) use ($self): void {
            $split = $self->get();
            if (is_null($split) || $split->isRemoved()) {
                Rtimer_destroy($timer);

                return;
            }
            $split->compare();
        }, null);
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
        Rslot_set_fill($child->native(), 1.0);
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
    }

    protected function applyPosition(float $position): void
    {
        $panes = $this->children();
        if (count($panes) === 2) {
            Rslot_set_fill($panes[0]->native(), max(0.0001, $position));
            Rslot_set_fill($panes[1]->native(), max(0.0001, 1.0 - $position));
        }
    }

    protected function childLayoutChanged(TKPrimitive $child): void
    {
        $this->tellExpansion();
    }

    protected function releaseNative(): void
    {
        if ($this->watching !== 0 && Rtimer_is_valid($this->watching) === R_TRUE) {
            Rtimer_destroy($this->watching);
        }
        $this->watching = 0;
        $this->releaseWidget();
    }

    private function compare(): void
    {
        if (! $this->hasDivider() || Rwidget_is_valid($this->control) !== R_TRUE) {
            return;
        }
        $window = Rwidget_get_window($this->control);
        if ($window === R_INVALID_ID || $window === 0) {
            return;
        }

        $down = Rwindow_is_mouse_button_down($window, R_MOUSE_LEFT) === R_TRUE;
        $looking = $down || $this->was_down;
        $this->was_down = $down;

        $lengths = [];
        foreach ($this->children() as $pane) {
            Rwidget_get_rect($pane->native(), $x, $y, $width, $height);
            $lengths[] = $this->isHorizontal() ? $width : $height;
        }
        $length = array_sum($lengths);
        $resized = abs($length - $this->length) >= 0.5;
        $this->length = $length;

        if ($looking && ! $resized && $length > 0) {
            $this->dragged($lengths[0] / $length);
        }
    }
}
