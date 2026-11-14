<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Surface\Contracts\Toolkits\Mail\Views\ValueChanged;
use Surface\Toolkits\Primitives\TKSlider;

class RavenSlider extends TKSlider implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, float $min, float $max, float $value)
    {
        parent::__construct($name, $window, $parent, $placement, $min, $max, $value);
        $slider = $this->adoptNative(Rwidget_create(R_WIDGET_SLIDER));
        Rslider_set_range($slider, $min, $max);
        Rslider_set_value($slider, $value);
        $self = WeakReference::create($this);
        Rslider_set_changed_callback($slider, static function (int $slider, float $value) use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->nativeValueChanged($value);
                $primitive->session()->post(new ValueChanged($primitive->window->name(), $primitive->path(), $primitive->uuid, $value));
            }
        }, null);
    }

    protected function applyValue(float $value): void
    {
        $this->applying(fn () => Rslider_set_value($this->control, $value));
    }

    protected function applyRange(float $min, float $max): void
    {
        $this->applying(fn () => Rslider_set_range($this->control, $min, $max));
    }
}
