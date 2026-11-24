<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKProgressBar;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven progress bar. Raven has none for "not known", so a Raven timer sweeps it.
 */
class RavenProgressBar extends TKProgressBar
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    private const float SWEEP_SECONDS = 1.5;

    private int $sweep = 0;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_PROGRESSBAR));
        Rwidget_set_min_size($this->control, 120, 0);
        // A bar, however tall its slot: a row's would be a block.
        Rslot_set_valign($this->control, R_VALIGN_CENTER);
        $this->applyFraction($this->fraction());
    }

    protected function applyFraction(?float $fraction): void
    {
        if (! is_null($fraction)) {
            $this->stopSweep();
            Rprogress_bar_set_value($this->control, $fraction);

            return;
        }
        if ($this->sweep !== 0) {
            return;
        }

        Rprogress_bar_set_value($this->control, 0.0);
        $self = WeakReference::create($this);
        $start = Rget_time();
        $this->sweep = Rtimer_create(1 / 30, R_TRUE, static function (int $timer) use ($self, $start): void {
            $bar = $self->get();
            if (is_null($bar) || $bar->isRemoved() || Rwidget_is_valid($bar->control) !== R_TRUE) {
                Rtimer_destroy($timer);

                return;
            }
            Rprogress_bar_set_value($bar->control, fmod(Rget_time() - $start, self::SWEEP_SECONDS) / self::SWEEP_SECONDS);
        }, null);
    }

    protected function releaseNative(): void
    {
        $this->stopSweep();
        $this->releaseWidget();
    }

    private function stopSweep(): void
    {
        if ($this->sweep !== 0) {
            if (Rtimer_is_valid($this->sweep) === R_TRUE) {
                Rtimer_destroy($this->sweep);
            }
            $this->sweep = 0;
        }
    }
}
