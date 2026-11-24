<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKTable;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven table in a border, its columns sharing the width. Cells go in one by one: a cell
 * may hold the '|' that Rtable_add_row() splits on.
 */
class RavenTable extends TKTable
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_TABLE));
        foreach ($this->columns() as $index => $column) {
            Rtable_add_column($this->control, $column->label, 0);
            Rtable_set_column_fill($this->control, $index, 1.0);
        }
        Rtable_set_striped($this->control, R_TRUE);
        $this->write(fn () => $this->applyRows($this->cellRows()));

        $self = WeakReference::create($this);
        Rtable_set_selection_changed_callback($this->control, static function (int $table, int $row) use ($self): void {
            $self->get()?->choseRow($row < 0 ? null : $row);
        }, null);
    }

    protected function applyRows(array $cells): void
    {
        Rtable_clear_rows($this->control);
        foreach ($cells as $row => $texts) {
            Rtable_add_row($this->control, null);
            foreach ($texts as $column => $text) {
                Rtable_set_cell($this->control, $row, $column, $text);
            }
        }
    }

    protected function applySelectedRow(?int $row): void
    {
        Rtable_set_selected($this->control, $row ?? -1);
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rtable_set_selection_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
