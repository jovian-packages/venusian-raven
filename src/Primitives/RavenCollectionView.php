<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenItemView;
use Surface\Contracts\Toolkits\Primitives\CollectionItem;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKCollectionView;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;

/** Cells in a Raven wrap box: the image filling the cell, the caption under it, icons and the action over its top-right corner. */
class RavenCollectionView extends TKCollectionView implements RavenNative
{
    use RavenItemView;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, array $items)
    {
        parent::__construct($name, $window, $parent, $placement, $items);
        $this->buildItemView(true);
    }

    protected function itemWidget(object $item, int $index): int
    {
        /** @var CollectionItem $item */
        $cell = Rwidget_create(R_WIDGET_BORDER);
        Rborder_set_padding($cell, 6, 6, 6, 6);
        Rwidget_set_min_size($cell, $this->cell_width, $this->cell_height);
        Rwidget_set_max_size($cell, $this->cell_width, $this->cell_height);
        $stack = Rwidget_add_child($cell, Rwidget_create(R_WIDGET_VBOX));
        Rpanel_set_spacing($stack, 4);

        $media = Rwidget_add_child($stack, Rwidget_create(R_WIDGET_OVERLAY));
        Rslot_set_fill($media, 1.0);
        $picture = Rwidget_add_child($media, $this->picture($item->image, $this->cell_width - 12, max(16, $this->cell_height - 36), 8));
        Rwidget_set_max_size($picture, $this->cell_width - 12, max(16, $this->cell_height - 36));
        $corner = Rwidget_add_child($media, Rwidget_create(R_WIDGET_HBOX));
        Rpanel_set_spacing($corner, 2);
        Rslot_set_halign($corner, R_HALIGN_RIGHT);
        Rslot_set_valign($corner, R_VALIGN_TOP);
        foreach ($item->icons as $icon) {
            Rwidget_add_child($corner, $this->picture($icon, 16, 16));
        }
        if (! is_null($item->action)) {
            Rwidget_add_child($corner, $this->actionButton($index, null, $item->action));
        }

        $caption = Rwidget_add_child($stack, Rlabel_create($item->caption));
        Rlabel_set_text_align($caption, R_TEXT_ALIGN_CENTER);
        Rslot_set_halign($caption, R_HALIGN_FILL);

        return $cell;
    }

    protected function applyCellSize(int $width, int $height): void
    {
        $this->applyItems($this->items);
    }
}
