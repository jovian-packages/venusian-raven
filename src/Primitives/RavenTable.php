<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Mail\Views\RowSelected;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\TableColumn;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Toolkits\Primitives\TKTable;
use WeakReference;

/** A Raven table: a header per column, columns sharing the width, one selected row. */
class RavenTable extends TKTable implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, array $columns, array $rows)
    {
        parent::__construct($name, $window, $parent, $placement, $columns, $rows);
        $table = $this->adoptNative(Rwidget_create(R_WIDGET_TABLE));
        foreach ($this->columns as $index => $column) {
            /** @var TableColumn $column */
            Rtable_add_column($table, $column->label, 0);
            Rtable_set_column_fill($table, $index, 1.0);
        }
        Rtable_set_striped($table, R_TRUE);
        $this->applyRows($this->cellRows());
        $self = WeakReference::create($this);
        Rtable_set_selection_changed_callback($table, static function (int $table, int $row) use ($self): void {
            $primitive = $self->get();
            if (! $primitive?->handling()) {
                return;
            }
            $selected = $row < 0 ? null : $row;
            $primitive->nativeRowSelected($selected);
            $primitive->session()->post(new RowSelected($primitive->window->name(), $primitive->path(), $primitive->uuid, $selected, is_null($selected) ? null : $primitive->rows[$selected]));
        }, null);
    }

    /** Cells by Rtable_set_cell: a cell may hold the '|' Rtable_add_row splits on. */
    protected function applyRows(array $cells): void
    {
        $this->applying(function () use ($cells): void {
            Rtable_clear_rows($this->control);
            foreach ($cells as $row => $texts) {
                Rtable_add_row($this->control, null);
                foreach (array_values($texts) as $column => $text) {
                    Rtable_set_cell($this->control, $row, $column, $text);
                }
            }
        });
    }

    protected function applySelectedRow(?int $row): void
    {
        $this->applying(fn () => Rtable_set_selected($this->control, $row ?? -1));
    }
}
