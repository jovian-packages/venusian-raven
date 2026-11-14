<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use DateTimeImmutable;
use Surface\Contracts\Toolkits\Mail\Views\DateChanged;
use Surface\Toolkits\Primitives\TKDatepicker;

/** Raven's date picker. It always shows a date: with none set it shows today and reports none until the user picks. */
class RavenDatepicker extends TKDatepicker implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, ?DateTimeImmutable $date)
    {
        parent::__construct($name, $window, $parent, $placement, $date);
        $picker = $this->adoptNative(Rwidget_create(R_WIDGET_DATE_PICKER));
        $this->applyDate($date);
        $self = WeakReference::create($this);
        Rdate_picker_set_changed_callback($picker, static function (int $picker, int $year, int $month, int $day) use ($self): void {
            $primitive = $self->get();
            if (! $primitive?->handling()) {
                return;
            }
            $date = (new DateTimeImmutable('today'))->setDate($year, $month, $day);
            $primitive->nativeDateChanged($date);
            $primitive->session()->post(new DateChanged($primitive->window->name(), $primitive->path(), $primitive->uuid, $date));
        }, null);
    }

    protected function applyDate(?DateTimeImmutable $date): void
    {
        $shown = $date ?? new DateTimeImmutable('today');
        $this->applying(fn () => Rdate_picker_set_date($this->control, (int) $shown->format('Y'), (int) $shown->format('n'), (int) $shown->format('j')));
    }
}
