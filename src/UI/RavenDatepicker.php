<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use DateTimeImmutable;
use Surface\Toolkits\UI\TKDatepicker;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * Raven's date picker in a border: a field that opens a calendar.
 */
class RavenDatepicker extends TKDatepicker
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    protected function bootstrapNative(): void
    {
        $picker = Rwidget_create(R_WIDGET_DATE_PICKER);
        Rdate_picker_set_range($picker, self::FIRST_YEAR, 1, 1, self::LAST_YEAR, 12, 31);
        $this->adoptNative($picker);
        $this->applyDate($this->date());

        $self = WeakReference::create($this);
        Rdate_picker_set_changed_callback($picker, static function (int $picker, int $year, int $month, int $day) use ($self): void {
            $self->get()?->picked($year, $month, $day);
        }, null);
    }

    protected function applyDate(?DateTimeImmutable $date): void
    {
        $day = $this->shownDay();
        Rdate_picker_set_date($this->control, (int) $day->format('Y'), (int) $day->format('n'), (int) $day->format('j'));
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rdate_picker_set_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
