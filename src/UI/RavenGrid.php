<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKGrid;
use Surface\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\Insets;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven grid in a border; the border's padding, each side its own, is the grid's.
 */
class RavenGrid extends TKGrid
{
    use RavenNative;

    /** Rows and columns a fill was set on, to clear. */
    private int $rows = 0;

    private int $columns = 0;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_GRID));
        Rpanel_set_spacing($this->control, $this->spacing());
        $this->applyPadding($this->padding());
    }

    protected function attachNative(TKPrimitive $child): void
    {
        Rwidget_add_child($this->control, $child->native());
        $this->put($child);
        $this->restretch();
    }

    protected function detachNative(TKPrimitive $child): void
    {
        if (Rwidget_is_valid($this->control) === R_TRUE && Rwidget_is_valid($child->native()) === R_TRUE) {
            Rwidget_remove_child($this->control, $child->native());
        }
        $this->restretch();
    }

    protected function applyCells(): void
    {
        foreach ($this->children() as $child) {
            $this->put($child);
        }
        $this->restretch();
    }

    protected function applySpacing(int $spacing): void
    {
        Rpanel_set_spacing($this->control, $spacing);
    }

    /**
     * The border's padding, each side its own, inside the background.
     */
    protected function applyPadding(Insets $padding): void
    {
        Rborder_set_padding($this->native, $padding->left, $padding->top, $padding->right, $padding->bottom);
    }

    protected function childLayoutChanged(TKPrimitive $child): void
    {
        self::placeAlignment($child->native(), $child->slotAligned());
        $this->restretch();
    }

    protected function childVisibilityChanged(TKPrimitive $child): void
    {
        $this->restretch();
    }

    private function put(TKPrimitive $child): void
    {
        $cell = $this->cellOf($child);
        Rslot_set_grid($child->native(), $cell->column, $cell->row);
        Rslot_set_grid_span($child->native(), $cell->columnSpan, $cell->rowSpan);
        self::placeAlignment($child->native(), $child->slotAligned());
    }

    private function restretch(): void
    {
        [$rows, $columns] = [[], []];
        foreach ($this->children() as $child) {
            if (! $child->isVisible()) {
                continue;
            }
            $cell = $this->cellOf($child);
            [$across, $down] = [$child->expands(true), $child->expands(false)];
            for ($row = $cell->row; $row < $cell->row + $cell->rowSpan; $row++) {
                $rows[$row] = ($rows[$row] ?? false) || $down;
            }
            for ($column = $cell->column; $column < $cell->column + $cell->columnSpan; $column++) {
                $columns[$column] = ($columns[$column] ?? false) || $across;
            }
        }

        $this->rows = max($this->rows, $rows === [] ? 0 : max(array_keys($rows)) + 1);
        $this->columns = max($this->columns, $columns === [] ? 0 : max(array_keys($columns)) + 1);
        for ($row = 0; $row < $this->rows; $row++) {
            Rgrid_set_row_fill($this->control, $row, ($rows[$row] ?? false) ? 1.0 : 0.0);
        }
        for ($column = 0; $column < $this->columns; $column++) {
            Rgrid_set_column_fill($this->control, $column, ($columns[$column] ?? false) ? 1.0 : 0.0);
        }
        $this->tellExpansion();
    }
}
