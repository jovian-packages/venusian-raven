<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKCollectionView;
use Surface\Contracts\Toolkits\UI\CollectionItem;
use Jovian\Toolkits\Raven\UI\Concerns\RavenItemView;

/**
 * A scroll box over a wrap box of cells.
 */
class RavenCollectionView extends TKCollectionView
{
    use RavenItemView;

    protected function bootstrapNative(): void
    {
        $this->buildItemView(true);
    }

    protected function itemWidget(object $item, int $index): int
    {
        /** @var CollectionItem $item */
        $cell = Rwidget_create(R_WIDGET_BORDER);
        Rborder_set_padding($cell, 6, 6, 6, 6);
        Rborder_set_background($cell, 0, 0, 0, 0);
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
