<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKDropdown;
use Jovian\Toolkits\Raven\UI\Support\RavenIcon;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven combo box inside a border. Its changed callback tells topside the chosen index.
 * Raven may report a change while the items are replaced; that comes while $rebuilding and
 * is not the user's, so it is not passed on.
 *
 * Raven's combo box draws no icon on its items, so while an option has an icon, or it is
 * icon-only, the border holds a Raven button instead: the selected option's icon, its label
 * unless icon-only, and Raven's chevron-down, the mark its combo box draws. Framed as a combo
 * box is; flat while icon-only. A press opens a Raven menu under it (R_WIDGET_MENU, opened with
 * Rmenu_open), each item an option with its icon, the selected one checked as the combo box's
 * list checks it. The button is the control while it is shown.
 */
class RavenDropdown extends TKDropdown
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    private bool $rebuilding = false;

    private int $combo = 0;

    /** The button shown instead of the combo box; 0 until there is a reason for one. */
    private int $button = 0;

    /** The menu the button opened last; 0 before. */
    private int $menu = 0;

    /** @var list<int> textures read from files for the button, destroyed when it is drawn again */
    private array $textures = [];

    /** @var list<int> textures read from files for the menu, destroyed with it */
    private array $menu_textures = [];

    /** Below the button by as much as Raven's combo box opens its list below itself (combo_box.c: rect.h + 2). */
    private const float MENU_GAP = 2.0;

    /**
     * The callback holds the primitive weakly, as RavenCheckbox's does.
     */
    protected function bootstrapNative(): void
    {
        $this->combo = Rwidget_create(R_WIDGET_COMBO_BOX);
        $this->adoptNative($this->combo);
        $this->applyOptions($this->options(), $this->selected());

        $self = WeakReference::create($this);
        Rcombo_box_set_changed_callback($this->combo, static function (int $combo, int $index) use ($self): void {
            $dropdown = $self->get();
            if (! is_null($dropdown) && ! $dropdown->rebuilding) {
                $dropdown->chosen($index);
            }
        }, null);
    }

    protected function releaseNative(): void
    {
        if ($this->combo !== 0 && Rwidget_is_valid($this->combo) === R_TRUE) {
            Rcombo_box_set_changed_callback($this->combo, null, null);
        }
        if ($this->button !== 0 && Rwidget_is_valid($this->button) === R_TRUE) {
            Rbutton_set_clicked_callback($this->button, null, null);
        }
        // The face not in the border goes with it only when it is detached here.
        $detached = $this->control === $this->button ? $this->combo : $this->button;
        $this->dropMenu();
        $this->releaseWidget();
        if ($detached !== 0 && Rwidget_is_valid($detached) === R_TRUE) {
            Rwidget_destroy($detached);
        }
        $this->dropTextures();
        $this->combo = $this->button = 0;
    }

    protected function applyOptions(array $options, int $selected): void
    {
        $this->rebuilding = true;
        try {
            Rcombo_box_clear_items($this->combo);
            foreach ($options as $option) {
                Rcombo_box_add_item($this->combo, $option);
            }
            if ($selected >= 0) {
                Rcombo_box_set_selected($this->combo, $selected);
            }
        } finally {
            $this->rebuilding = false;
        }
        $this->showFace();
    }

    protected function applySelected(int $index): void
    {
        Rcombo_box_set_selected($this->combo, $index);
        $this->showFace();
    }

    protected function applyIcons(array $icons): void
    {
        $this->showFace();
    }

    protected function applyIconOnly(bool $on): void
    {
        $this->showFace();
    }

    /** The combo box, or the button while an option has an icon or it is icon-only. */
    private function showFace(): void
    {
        $wanted = $this->usesButton() ? $this->button() : $this->combo;
        if ($wanted !== $this->control) {
            $focused = $this->hasFocus();
            Rwidget_remove_child($this->native, $this->control);
            Rwidget_add_child($this->native, $wanted);
            $this->control = $wanted;
            if ($focused) {
                Rwidget_focus($wanted);
            }
        }
        if ($wanted === $this->button) {
            $this->drawButton();
        }
    }

    private function usesButton(): bool
    {
        if ($this->isIconOnly()) {
            return true;
        }
        foreach (array_keys($this->options()) as $index) {
            if (! is_null($this->iconOf($index))) {
                return true;
            }
        }

        return false;
    }

    private function button(): int
    {
        if ($this->button === 0) {
            $this->button = Rbutton_create(null);
            $self = WeakReference::create($this);
            Rbutton_set_clicked_callback($this->button, static function () use ($self): void {
                $self->get()?->openMenu();
            }, null);
        }

        return $this->button;
    }

    /** The selected option's icon, its label unless icon-only, and the chevron. */
    private function drawButton(): void
    {
        Rbutton_set_style($this->button, $this->isIconOnly() ? R_BUTTON_FLAT : R_BUTTON_DEFAULT);
        $old = Rwidget_get_child($this->button, 0);
        if ($old !== 0) {
            Rwidget_remove_child($this->button, $old);
            Rwidget_destroy($old);
        }
        $this->dropTextures();

        $box = Rwidget_create(R_WIDGET_HBOX);
        Rpanel_set_spacing($box, Rwidget_get_metric($this->button, R_METRIC_SPACING));
        $selected = $this->selected();
        $icon = $selected < 0 ? null : $this->iconOf($selected);
        if (! is_null($icon)) {
            $this->addImage($box, $this->texture($icon['file'], $icon['symbol'], $this->textures));
        }
        if (! $this->isIconOnly()) {
            $label = Rlabel_create($this->selectedOption() ?? '');
            Rwidget_add_child($box, $label);
            Rslot_set_valign($label, R_VALIGN_CENTER);
        }
        $this->addImage($box, Ricon_get('chevron-down'));
        Rwidget_add_child($this->button, $box);
    }

    private function addImage(int $box, int $texture): void
    {
        if ($texture === R_INVALID_ID) {
            return;
        }
        $image = Rwidget_create(R_WIDGET_IMAGE);
        Rimage_set_stretch($image, R_STRETCH_FIT);
        Rimage_set_texture($image, $texture);
        // Square, a quarter taller than the text, as a button's icon.
        $side = ceil(Rwidget_get_metric($this->button, R_METRIC_FONT_SIZE) * 1.25);
        Rwidget_set_size($image, $side, $side);
        Rwidget_add_child($box, $image);
        Rslot_set_valign($image, R_VALIGN_CENTER);
    }

    /** A menu of the options under the button, the selected one checked. */
    private function openMenu(): void
    {
        if (! $this->takesInput() || $this->isRemoved()) {
            return;
        }
        $this->dropMenu();
        $this->menu = Rwidget_create(R_WIDGET_MENU);
        $self = WeakReference::create($this);
        foreach ($this->options() as $index => $option) {
            $item = Rmenu_add_item($this->menu, $option);
            Rmenu_item_set_checkable($item, R_TRUE);
            Rmenu_item_set_checked($item, $index === $this->selected() ? R_TRUE : R_FALSE);
            $icon = $this->iconOf($index);
            if (! is_null($icon)) {
                $texture = $this->texture($icon['file'], $icon['symbol'], $this->menu_textures);
                if ($texture !== R_INVALID_ID) {
                    // Raven's icons are masks drawn in the text color; a file is drawn as it is.
                    Rmenu_item_set_icon($item, $texture, 0.0, 0.0, 1.0, 1.0, is_null($icon['file']) ? R_TRUE : R_FALSE);
                }
            }
            Rmenu_item_set_clicked_callback($item, static function () use ($self, $index): void {
                $self->get()?->picked($index);
            }, null);
        }
        Rwidget_get_rect($this->button, $x, $y, $width, $height);
        Rmenu_open($this->menu, Rwidget_get_window($this->button), (float) $x, (float) $y + (float) $height + self::MENU_GAP);
    }

    /** An item of the menu was chosen: topside is told, and the combo box takes it so both faces agree. */
    private function picked(int $index): void
    {
        $this->chosen($index);
        $this->rebuilding = true;
        try {
            Rcombo_box_set_selected($this->combo, $index);
        } finally {
            $this->rebuilding = false;
        }
        $this->showFace();
    }

    /**
     * @param list<int> $kept where a texture read from a file goes, to be destroyed later
     * @return int the texture, R_INVALID_ID for none
     */
    private function texture(?string $file, ?string $symbol, array &$kept): int
    {
        [$texture, $owned] = RavenIcon::texture($file, $symbol);
        if ($owned) {
            $kept[] = $texture;
        }

        return $texture;
    }

    private function dropMenu(): void
    {
        if ($this->menu !== 0 && Rwidget_is_valid($this->menu) === R_TRUE) {
            Rwidget_destroy($this->menu);
        }
        $this->menu = 0;
        self::destroy($this->menu_textures);
    }

    private function dropTextures(): void
    {
        self::destroy($this->textures);
    }

    /** @param list<int> $textures */
    private static function destroy(array &$textures): void
    {
        foreach ($textures as $texture) {
            if (Rtexture_is_valid($texture) === R_TRUE) {
                Rtexture_destroy($texture);
            }
        }
        $textures = [];
    }
}
