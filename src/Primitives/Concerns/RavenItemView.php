<?php

namespace Jovian\Toolkits\Raven\Primitives\Concerns;

use REvent;
use Surface\Contracts\Toolkits\Mail\Views\ItemActionClicked;
use Surface\Contracts\Toolkits\Mail\Views\ItemActivated;
use Surface\Contracts\Toolkits\Mail\Views\ViewScrolled;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;
use WeakReference;

/**
 * List and collection views: Raven's list and tile views draw text rows and tiles but hold no
 * buttons, so each item is a small tree of Raven widgets in a scroll box (a column of rows, or a
 * wrap box of cells). A double click activates an item; its action button posts
 * ItemActionClicked; a disabled item is dimmed and posts nothing. Raven reports no scrolling,
 * so the offset is compared after each pump while the scroll is watched.
 */
trait RavenItemView
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
    }

    /** The box inside the scroll box that holds one widget per item. */
    protected int $items_box = 0;

    /** @var list<int> each item's widget, in order */
    protected array $item_widgets = [];

    /** @var array<string, int> image file => texture */
    protected array $textures = [];

    /** @var array{float, float}|null the offset last seen */
    protected ?array $scroll_seen = null;

    /** Build one item's widget. */
    abstract protected function itemWidget(object $item, int $index): int;

    protected function buildItemView(bool $wrap): void
    {
        $scroll = $this->adoptNative(Rwidget_create(R_WIDGET_SCROLL_BOX));
        Rscroll_box_set_orientation($scroll, R_VERTICAL);
        $this->items_box = Rwidget_add_child($scroll, Rwidget_create($wrap ? R_WIDGET_WRAP_BOX : R_WIDGET_VBOX));
        Rslot_set_halign($this->items_box, R_HALIGN_FILL);
        Rslot_set_valign($this->items_box, R_VALIGN_TOP);
        $this->applyItems($this->items);
        $self = WeakReference::create($this);
        $this->session()->afterPump("items.{$this->uuid}", static fn () => $self->get()?->scrolled());
    }

    protected function applyItems(array $items): void
    {
        Rwidget_clear_children($this->items_box);
        $this->item_widgets = [];
        foreach (array_values($items) as $index => $item) {
            $this->item_widgets[] = Rwidget_add_child($this->items_box, $this->wired($item, $index));
        }
    }

    protected function applyInserted(int $at, array $items): void
    {
        // Rebuilt from $at: each widget carries its index for its mail.
        $this->rebuildFrom($at);
    }

    protected function applyRemoved(int $at, int $count): void
    {
        $this->rebuildFrom($at);
    }

    protected function applyUpdated(int $index, object $item): void
    {
        $this->rebuildFrom($index, $index + 1);
    }

    protected function rebuildFrom(int $from, ?int $to = null): void
    {
        $items = array_values($this->items);
        $to ??= max(count($items), count($this->item_widgets));
        for ($i = $from; $i < $to; $i++) {
            if (isset($this->item_widgets[$i]) && Rwidget_is_valid($this->item_widgets[$i]) === R_TRUE) {
                Rwidget_destroy($this->item_widgets[$i]);
            }
            unset($this->item_widgets[$i]);
            if (isset($items[$i])) {
                $this->item_widgets[$i] = Rwidget_insert_child($this->items_box, $this->wired($items[$i], $i), $i);
            }
        }
        ksort($this->item_widgets);
        $this->item_widgets = array_values($this->item_widgets);
    }

    /** The item's widget, dimmed when disabled, activated by a double click. */
    protected function wired(object $item, int $index): int
    {
        $widget = $this->itemWidget($item, $index);
        if (! $this->enabled($index)) {
            Rwidget_set_enabled($widget, R_FALSE);
            Rwidget_set_opacity($widget, 0.45);
        }
        $self = WeakReference::create($this);
        Rwidget_set_event_callback($widget, static function (int $widget, REvent $event) use ($self, $index): int {
            if ($event->type === R_EVENT_MOUSE_DOWN && $event->button === R_MOUSE_LEFT && $event->clicks === 2) {
                $self->get()?->activate($index);
            }

            return R_FALSE;
        }, null);

        return $widget;
    }

    /** The item's action button: posts ItemActionClicked. */
    protected function actionButton(int $index, ?string $label, ?string $image): int
    {
        $button = Rbutton_create($label ?? '');
        if (! is_null($image)) {
            $picture = Rwidget_add_child($button, Rwidget_create(R_WIDGET_IMAGE));
            Rimage_set_texture($picture, $this->texture($image));
            Rwidget_set_min_size($picture, 16, 16);
            Rwidget_set_max_size($picture, 16, 16);
            Rbutton_set_style($button, R_BUTTON_FLAT);
        }
        $self = WeakReference::create($this);
        Rbutton_set_clicked_callback($button, static function () use ($self, $index): void {
            $view = $self->get();
            if ($view?->handling() && $view->enabled($index)) {
                $view->session()->post(new ItemActionClicked($view->window->name(), $view->path(), $view->uuid, $index));
            }
        }, null);

        return $button;
    }

    /** An image widget showing $file, cropped to cover $width × $height. */
    protected function picture(?string $file, float $width, float $height, float $radius = 0.0): int
    {
        $picture = Rwidget_create(R_WIDGET_IMAGE);
        Rwidget_set_min_size($picture, $width, $height);
        Rwidget_set_max_size($picture, $width, $height);
        Rimage_set_stretch($picture, R_STRETCH_COVER);
        Rimage_set_corner_radius($picture, $radius);
        if (! is_null($file)) {
            Rimage_set_texture($picture, $this->texture($file));
        }

        return $picture;
    }

    /** One texture per file for the view's life; a file Raven cannot read shows nothing. */
    protected function texture(string $file): int
    {
        if (! isset($this->textures[$file])) {
            $texture = is_file($file) ? Rtexture_create_from_file($file) : 0;
            $this->textures[$file] = Rtexture_is_valid($texture) === R_TRUE ? $texture : 0;
        }

        return $this->textures[$file];
    }

    public function activate(int $index): void
    {
        if ($this->handling() && $this->enabled($index)) {
            $this->session()->post(new ItemActivated($this->window->name(), $this->path(), $this->uuid, $index));
        }
    }

    protected function nativeScrollPosition(): ScrollPosition
    {
        Rscroll_box_get_offset($this->control, $x, $y);
        Rwidget_get_rect($this->control, $vx, $vy, $visible_width, $visible_height);
        Rwidget_get_desired_size($this->items_box, $content_width, $content_height);

        return ScrollPosition::of($x, $visible_width, max($visible_width, $content_width), $y, $visible_height, max($visible_height, $content_height));
    }

    protected function applyScrollTo(float $x, float $y): void
    {
        Rwidget_get_rect($this->control, $vx, $vy, $visible_width, $visible_height);
        Rwidget_get_desired_size($this->items_box, $content_width, $content_height);
        $this->applying(fn () => Rscroll_box_set_offset(
            $this->control,
            ScrollPosition::offset($x, $visible_width, $content_width),
            ScrollPosition::offset($y, $visible_height, $content_height),
        ));
        Rscroll_box_get_offset($this->control, $ox, $oy);
        $this->scroll_seen = [$ox, $oy];
    }

    public function scrolled(): void
    {
        if ($this->removed) {
            return;
        }
        Rscroll_box_get_offset($this->control, $x, $y);
        if ($this->scroll_seen === [$x, $y]) {
            return;
        }
        $this->scroll_seen = [$x, $y];
        if ($this->watching_scroll) {
            $this->session()->postLatest(
                "view.scrolled.{$this->window->name()}.{$this->path()}",
                new ViewScrolled($this->window->name(), $this->path(), $this->uuid, $this->nativeScrollPosition()),
            );
        }
    }

    protected function destroyNative(): void
    {
        $this->session()->afterPump("items.{$this->uuid}", null);
        $this->destroyPrimitive();
        foreach ($this->textures as $texture) {
            if ($texture !== 0) {
                Rtexture_destroy($texture);
            }
        }
        $this->textures = [];
    }
}
