<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenItemView;
use Surface\Contracts\Toolkits\Primitives\ListRow;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKListView;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;

class RavenListView extends TKListView implements RavenNative
{
    use RavenItemView;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, array $items)
    {
        parent::__construct($name, $window, $parent, $placement, $items);
        $this->buildItemView(false);
    }

    /** Image (badge over its corner) | title with status icons, detail under it | action button. */
    protected function itemWidget(object $item, int $index): int
    {
        /** @var ListRow $item */
        $row = Rwidget_create(R_WIDGET_BORDER);
        Rborder_set_padding($row, 8, 6, 8, 6);
        Rwidget_set_min_size($row, 0, $this->row_height);
        $line = Rwidget_add_child($row, Rwidget_create(R_WIDGET_HBOX));
        Rpanel_set_spacing($line, 10);
        $side = max(16, $this->row_height - 12);

        $media = Rwidget_add_child($line, Rwidget_create(R_WIDGET_OVERLAY));
        Rslot_set_valign($media, R_VALIGN_CENTER);
        Rwidget_add_child($media, $this->picture($item->image, $side, $side, 6));
        if (! is_null($item->badge)) {
            $badge = Rwidget_add_child($media, Rwidget_create(R_WIDGET_BORDER));
            Rborder_set_background($badge, 0, 0, 0, 0.65);
            Rborder_set_corner_radius($badge, 4, 4, 4, 4);
            Rborder_set_padding($badge, 4, 1, 4, 1);
            Rslot_set_halign($badge, R_HALIGN_LEFT);
            Rslot_set_valign($badge, R_VALIGN_TOP);
            $text = Rwidget_add_child($badge, Rlabel_create($item->badge));
            Rlabel_set_text_size($text, 10);
            Rlabel_set_color($text, 1, 1, 1, 1);
        }

        $texts = Rwidget_add_child($line, Rwidget_create(R_WIDGET_VBOX));
        Rslot_set_fill($texts, 1.0);
        Rslot_set_valign($texts, R_VALIGN_CENTER);
        Rpanel_set_spacing($texts, 2);
        $title_line = Rwidget_add_child($texts, Rwidget_create(R_WIDGET_HBOX));
        Rpanel_set_spacing($title_line, 4);
        $title = Rwidget_add_child($title_line, Rlabel_create($item->title));
        Rlabel_set_font($title, Rfont_get_variant(Rfont_get_default(), R_FONT_BOLD));
        foreach ($item->icons as $icon) {
            $picture = Rwidget_add_child($title_line, $this->picture($icon, 14, 14));
            Rslot_set_valign($picture, R_VALIGN_CENTER);
        }
        if ($item->detail !== '') {
            $detail = Rwidget_add_child($texts, Rlabel_create($item->detail));
            Rlabel_set_wrap($detail, R_TRUE);
            Rlabel_set_text_size($detail, 11);
            Rwidget_set_opacity($detail, 0.75);
        }

        if (! is_null($item->action)) {
            $button = Rwidget_add_child($line, $this->actionButton($index, $item->action, null));
            Rslot_set_valign($button, R_VALIGN_BOTTOM);
        }

        return $row;
    }

    protected function applyRowHeight(int $height): void
    {
        $this->applyItems($this->items);
    }
}
