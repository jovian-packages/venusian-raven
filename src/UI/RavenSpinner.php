<?php

namespace Jovian\Toolkits\Raven\UI;

use RPainter;
use WeakReference;
use Surface\Toolkits\UI\TKSpinner;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * Raven has no spinner: a custom widget paints a ring of dots, and asks to be painted again
 * for as long as it spins.
 */
class RavenSpinner extends TKSpinner
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    private const int SIZE = 20;

    private const int DOTS = 12;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_CUSTOM));
        Rwidget_set_min_size($this->control, self::SIZE, self::SIZE);

        $self = WeakReference::create($this);
        Rcustom_set_paint_callback($this->control, static function (int $widget, RPainter $painter, float $width, float $height) use ($self): void {
            $spinner = $self->get();
            if (is_null($spinner) || $spinner->isRemoved() || ! $spinner->isSpinning()) {
                return;
            }

            Rtheme_get_color(R_COLOR_TEXT, $r, $g, $b, $a);
            $radius = max(0.0, min($width, $height) / 2 - 2);
            $turn = fmod(Rget_time(), 1.0) * self::DOTS;
            for ($dot = 0; $dot < self::DOTS; $dot++) {
                $angle = $dot / self::DOTS * 2 * M_PI;
                $lit = fmod($dot - $turn + self::DOTS, self::DOTS) / self::DOTS;
                Rdraw_circle($painter, $width / 2 + cos($angle) * $radius, $height / 2 + sin($angle) * $radius, max(1.0, $radius * 0.2), $r, $g, $b, $a * (0.15 + 0.85 * $lit));
            }
            Rwidget_invalidate($widget);
        }, null);
    }

    protected function applySpinning(bool $spinning): void
    {
        Rwidget_invalidate($this->control);
    }

    /** The callback goes first: ext-raven would otherwise hold it until it next sweeps. */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rcustom_set_paint_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
