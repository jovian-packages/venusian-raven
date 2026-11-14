<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenContainer;
use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive as Child;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Toolkits\Primitives\TKGrid;

/**
 * A Raven grid: each child in its cell and span. A row or column holding a child that fills
 * that axis takes a share of the spare space; with none filling, the spare space stays after
 * the last row and column.
 */
class RavenGrid extends TKGrid implements RavenContainer
{
    use RavenPrimitive;

    /** @var array<int, true> rows and columns given a fill, to clear on the next pass */
    protected array $filled_rows = [];

    protected array $filled_columns = [];

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, int $spacing, int $padding)
    {
        parent::__construct($name, $window, $parent, $placement, $spacing, $padding);
        $grid = $this->adoptNative(Rwidget_create(R_WIDGET_GRID));
        Rpanel_set_spacing($grid, $spacing);
        Rborder_set_padding($this->native, $padding, $padding, $padding, $padding);
    }

    public function placeChild(Child&RavenNative $child): void
    {
        [$horizontal, $vertical] = $child->fills();
        [$halign, $valign] = $child->ravenAlign();
        Rslot_set_halign($child->native(), $horizontal ? R_HALIGN_FILL : $halign);
        Rslot_set_valign($child->native(), $vertical ? R_VALIGN_FILL : $valign);
        $this->restretch();
    }

    public function forgetChild(Child $child): void
    {
        parent::forgetChild($child);
        $this->restretch();
    }

    protected function insertNative(Child $child): void
    {
        $cell = $child->placement();
        $native = self::nativeOf($child);
        Rwidget_add_child($this->control, $native);
        Rslot_set_grid($native, $cell->column, $cell->row);
        Rslot_set_grid_span($native, $cell->columnSpan, $cell->rowSpan);
        if ($child instanceof RavenNative) {
            $this->placeChild($child);
        }
    }

    protected function restretch(): void
    {
        foreach (array_keys($this->filled_rows) as $row) {
            Rgrid_set_row_fill($this->control, $row, 0.0);
        }
        foreach (array_keys($this->filled_columns) as $column) {
            Rgrid_set_column_fill($this->control, $column, 0.0);
        }
        $this->filled_rows = [];
        $this->filled_columns = [];
        foreach ($this->children as $child) {
            [$horizontal, $vertical] = $child instanceof RavenNative ? $child->fills() : [false, false];
            $cell = $child->placement();
            for ($row = $cell->row; $vertical && $row < $cell->row + $cell->rowSpan; $row++) {
                $this->filled_rows[$row] = true;
            }
            for ($column = $cell->column; $horizontal && $column < $cell->column + $cell->columnSpan; $column++) {
                $this->filled_columns[$column] = true;
            }
        }
        foreach (array_keys($this->filled_rows) as $row) {
            Rgrid_set_row_fill($this->control, $row, 1.0);
        }
        foreach (array_keys($this->filled_columns) as $column) {
            Rgrid_set_column_fill($this->control, $column, 1.0);
        }
    }

    protected function applySpacing(int $spacing): void
    {
        Rpanel_set_spacing($this->control, $spacing);
    }
}
