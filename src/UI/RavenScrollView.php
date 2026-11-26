<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKPrimitive;
use WeakReference;
use Surface\Toolkits\UI\TKScrollView;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven scroll box. Its child is as long as it asks to be along the way it scrolls.
 */
class RavenScrollView extends TKScrollView
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /** The timer that compares the offset while scrolling is watched; 0 when none runs. */
    private int $watching = 0;

    /** @var array{float, float}|null the offset last compared */
    private ?array $scroll_seen = null;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_SCROLL_BOX));
        $this->applyScrollbars($this->scrollsAcross(), $this->scrollsDown());
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
        self::placeAlignment($child->native(), $child->slotAligned());
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

    /** The box's offset and rect against what its child asks to be. */
    protected function nativeScrollPosition(): ScrollPosition
    {
        $child = $this->content()?->native();
        if (is_null($child) || Rwidget_is_valid($child) !== R_TRUE) {
            return new ScrollPosition(0.0, 0.0, 1.0, 1.0);
        }
        Rscroll_box_get_offset($this->control, $x, $y);
        Rwidget_get_rect($this->control, $vx, $vy, $visible_width, $visible_height);
        Rwidget_get_desired_size($child, $content_width, $content_height);

        return ScrollPosition::of($x, $visible_width, max($visible_width, $content_width), $y, $visible_height, max($visible_height, $content_height));
    }

    /** Raven has no scroll callback: while watched, a timer compares the offset. */
    protected function applyWatchScroll(bool $on): void
    {
        if ($this->watching !== 0 && Rtimer_is_valid($this->watching) === R_TRUE) {
            Rtimer_destroy($this->watching);
        }
        $this->watching = 0;
        if (! $on) {
            return;
        }

        Rscroll_box_get_offset($this->control, $x, $y);
        $this->scroll_seen = [$x, $y];
        $self = WeakReference::create($this);
        $this->watching = Rtimer_create(1 / 30, R_TRUE, static function (int $timer) use ($self): void {
            $view = $self->get();
            if (is_null($view) || $view->isRemoved()) {
                Rtimer_destroy($timer);

                return;
            }
            $view->compareScroll();
        }, null);
    }

    protected function compareScroll(): void
    {
        if (Rwidget_is_valid($this->control) !== R_TRUE) {
            return;
        }
        Rscroll_box_get_offset($this->control, $x, $y);
        if ($this->scroll_seen !== [$x, $y]) {
            $this->scroll_seen = [$x, $y];
            $this->scrolled();
        }
    }

    protected function releaseNative(): void
    {
        if ($this->watching !== 0 && Rtimer_is_valid($this->watching) === R_TRUE) {
            Rtimer_destroy($this->watching);
        }
        $this->watching = 0;
        $this->releaseWidget();
    }

    protected function childLayoutChanged(TKPrimitive $child): void
    {
        self::placeAlignment($child->native(), $child->slotAligned());
        $this->tellExpansion();
    }

    protected function childVisibilityChanged(TKPrimitive $child): void
    {
        $this->tellExpansion();
    }
}
