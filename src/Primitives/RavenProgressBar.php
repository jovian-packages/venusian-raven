<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Surface\Toolkits\Primitives\TKProgressBar;

/**
 * A Raven progress bar. Raven has no indeterminate mode: a null fraction sweeps the bar from
 * empty to full on a timer until a fraction is set.
 */
class RavenProgressBar extends TKProgressBar implements RavenNative
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
    }

    protected int $sweep = 0;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, ?float $fraction)
    {
        parent::__construct($name, $window, $parent, $placement, $fraction);
        $this->adoptNative(Rwidget_create(R_WIDGET_PROGRESSBAR));
        $this->applyFraction($fraction);
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
        $self = WeakReference::create($this);
        $start = microtime(true);
        $this->sweep = Rtimer_create(1 / 30, R_TRUE, static function (int $timer) use ($self, $start): void {
            $primitive = $self->get();
            if (is_null($primitive) || $primitive->isRemoved()) {
                Rtimer_destroy($timer);

                return;
            }
            Rprogress_bar_set_value($primitive->control, fmod(microtime(true) - $start, 1.5) / 1.5);
        }, null);
    }

    protected function stopSweep(): void
    {
        if ($this->sweep !== 0) {
            Rtimer_destroy($this->sweep);
            $this->sweep = 0;
        }
    }

    protected function destroyNative(): void
    {
        $this->stopSweep();
        $this->destroyPrimitive();
    }
}
