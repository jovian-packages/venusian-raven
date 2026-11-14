<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use RPainter;
use Surface\Toolkits\Primitives\TKSpinner;

/** Raven has no spinner: a custom widget painting a turning arc of dots while it spins. */
class RavenSpinner extends TKSpinner implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement)
    {
        parent::__construct($name, $window, $parent, $placement);
        $custom = $this->adoptNative(Rwidget_create(R_WIDGET_CUSTOM));
        Rwidget_set_min_size($custom, 20, 20);
        $self = WeakReference::create($this);
        Rcustom_set_paint_callback($custom, static function (int $widget, RPainter $painter, float $width, float $height) use ($self): void {
            $primitive = $self->get();
            if (is_null($primitive) || ! $primitive->spinning) {
                return;
            }
            Rtheme_get_color(R_COLOR_TEXT, $r, $g, $b, $a);
            $radius = min($width, $height) / 2 - 2;
            $turn = fmod(Rget_time(), 1.0) * 12;
            for ($i = 0; $i < 12; $i++) {
                $angle = ($i / 12) * 2 * M_PI;
                $fade = fmod($i - $turn + 12, 12) / 12;
                Rdraw_circle($painter, $width / 2 + cos($angle) * $radius * 0.75, $height / 2 + sin($angle) * $radius * 0.75, max(1.0, $radius * 0.16), $r, $g, $b, $a * (0.15 + 0.85 * $fade));
            }
            // Painted again next frame while it spins.
            Rwidget_invalidate($widget);
        }, null);
    }

    protected function applySpinning(bool $spinning): void
    {
        Rwidget_invalidate($this->control);
    }
}
