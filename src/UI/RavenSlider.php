<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKSlider;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven slider inside a border.
 */
class RavenSlider extends TKSlider
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_SLIDER));
        Rwidget_set_min_size($this->control, 120, 0);
        Rslider_set_range($this->control, $this->min(), $this->max());
        Rslider_set_value($this->control, $this->value());

        $self = WeakReference::create($this);
        Rslider_set_changed_callback($this->control, static function (int $slider, float $value) use ($self): void {
            $self->get()?->slid($value);
        }, null);
    }

    protected function applyValue(float $value): void
    {
        Rslider_set_value($this->control, $value);
    }

    protected function applyRange(float $min, float $max, float $value): void
    {
        Rslider_set_range($this->control, $min, $max);
        Rslider_set_value($this->control, $value);
    }

    /** The callback goes first: ext-raven would otherwise hold it until it next sweeps. */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rslider_set_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
