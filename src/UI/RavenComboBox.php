<?php

namespace Jovian\Toolkits\Raven\UI;

use REvent;
use RuntimeException;
use WeakReference;
use Surface\Toolkits\UI\TKComboBox;
use Jovian\Toolkits\Raven\RavenWindow;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * Raven's editable combo box in a border; its text field is the control. The field's event
 * callback is the combo's own (it opens the list from the arrow): nothing here sets one.
 *
 *  - Options: the combo's list. Raven calls the changed callback for a pick and for typing an
 *    option's exact text, and only typing calls the text callback right after. So a change is
 *    held until the next frame, and is a choice unless the text callback came.
 *  - Completions: a menu opened as a popup, which leaves the field its focus and its typing.
 *    The popup says when it closes (Esc, a click outside), topside when focus moves away, and
 *    the window's input hook sees Return before the menu, so Return with nothing highlighted
 *    submits.
 */
class RavenComboBox extends TKComboBox
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    private const int NATURAL_WIDTH = 120;

    private int $combo = 0;

    /** The completions' menu while it shows; 0 for none. */
    private int $menu = 0;

    /** The menu last closed: destroyed later, since it may be in its own item's callback. */
    private int $retired = 0;

    /** A row of the completions is highlighted. */
    private bool $highlighted = false;

    /** An option that is selected and is not yet known to be typed or picked. */
    private ?int $pending = null;

    private int $settling = 0;

    /**
     * @throws RuntimeException When ext-raven is older than the editable combo box.
     */
    protected function bootstrapNative(): void
    {
        if (! function_exists('Rcombo_box_set_editable')) {
            throw new RuntimeException("A combo box needs ext-raven with Raven's editable combo box (0.11.x-nightly of 2026-11-19 or later): run its install script again.");
        }

        $this->combo = Rwidget_create(R_WIDGET_COMBO_BOX);
        Rwidget_set_min_size($this->combo, self::NATURAL_WIDTH, 0);
        Rcombo_box_set_editable($this->combo, R_TRUE);
        foreach ($this->options() as $option) {
            Rcombo_box_add_item($this->combo, $option);
        }
        Rcombo_box_set_text($this->combo, $this->value());

        $this->adoptNative($this->combo);
        // Editable, the combo takes no focus: its only child, the field, does.
        $this->control = Rwidget_get_child($this->combo, 0);

        $self = WeakReference::create($this);
        Rcombo_box_set_changed_callback($this->combo, static function (int $combo, int $index) use ($self): void {
            $self->get()?->selectionMoved($index);
        }, null);
        Rcombo_box_set_text_changed_callback($this->combo, static function (int $combo, ?string $text) use ($self): void {
            $self->get()?->edited((string) $text);
        }, null);
        Rtext_input_set_committed_callback($this->control, static function () use ($self): void {
            $self->get()?->committed();
        }, null);
    }

    protected function applyValue(string $value): void
    {
        $this->pending = null;
        Rcombo_box_set_text($this->combo, $value);
    }

    /**
     * Topside writes the value again after, which selects its option.
     */
    protected function applyOptions(array $options): void
    {
        $this->pending = null;
        Rcombo_box_clear_items($this->combo);
        foreach ($options as $option) {
            Rcombo_box_add_item($this->combo, $option);
        }
    }

    protected function applyCompletions(array $completions): void
    {
        $this->closeMenu();
        if ($completions !== []) {
            $this->openMenu($completions);
        }
    }

    protected function releaseNative(): void
    {
        $this->closeMenu();
        $this->destroyRetired();
        $this->pending = null;

        if ($this->settling !== 0 && Rtimer_is_valid($this->settling)) {
            Rtimer_destroy($this->settling);
        }
        $this->settling = 0;

        if ($this->combo !== 0 && Rwidget_is_valid($this->combo) === R_TRUE) {
            Rcombo_box_set_changed_callback($this->combo, null, null);
            Rcombo_box_set_text_changed_callback($this->combo, null, null);
            Rtext_input_set_committed_callback($this->control, null, null);
        }
        $this->combo = 0;

        $this->releaseWidget();
    }

    private function selectionMoved(int $index): void
    {
        $this->pending = $index >= 0 && $this->hearsUser() ? $index : null;
        if (is_null($this->pending) || $this->settling !== 0) {
            return;
        }

        $self = WeakReference::create($this);
        $this->settling = Rtimer_create(0.0, R_FALSE, static function () use ($self): void {
            $box = $self->get();
            if (! is_null($box)) {
                $box->settling = 0;
                $box->settleSelection();
            }
        }, null);
    }

    private function settleSelection(): void
    {
        $index = $this->pending;
        $this->pending = null;

        if (! is_null($index) && ! $this->isRemoved()) {
            $this->choseOption($index);
        }
    }

    private function edited(string $text): void
    {
        $this->pending = null;
        $this->typed($text);
    }

    private function committed(): void
    {
        if (! $this->isRemoved()) {
            $this->settleSelection();
            $this->submitted((string) Rcombo_box_get_text($this->combo));
        }
    }

    /**
     * @param list<string> $rows
     */
    private function openMenu(array $rows): void
    {
        $this->destroyRetired();
        Rcombo_box_set_open($this->combo, R_FALSE);

        $menu = Rwidget_create(R_WIDGET_MENU);
        $self = WeakReference::create($this);
        foreach ($rows as $text) {
            $item = Rmenu_add_item($menu, $text);
            Rmenu_item_set_clicked_callback($item, static function () use ($self, $text): void {
                $box = $self->get();
                if (! is_null($box) && ! $box->isRemoved()) {
                    $box->choseCompletion($text);
                }
            }, null);
        }

        $this->menu = $menu;
        $this->highlighted = false;

        Rwidget_get_rect($this->native, $x, $y, $width, $height);
        Rwidget_set_min_size($menu, $width, 0);
        Rpopup_open(Rwidget_get_window($this->native), $menu, $x, $y + $height, static function (int $closed) use ($self): void {
            $self->get()?->menuClosed($closed);
        }, null);

        $window = $this->window();
        if ($window instanceof RavenWindow) {
            $window->watchInput($this->uuid(), static fn (REvent $event): bool => $self->get()?->menuInput($event) ?? false);
        }
    }

    /**
     * An input event while the completions show, before the menu has it; true when taken.
     */
    private function menuInput(REvent $event): bool
    {
        if ($this->menu === 0) {
            return false;
        }

        if ($event->type === R_EVENT_MOUSE_MOVE && $this->isInMenu($event->target)) {
            $this->highlighted = true;
        }
        if ($event->type !== R_EVENT_KEY_DOWN) {
            return false;
        }
        if (in_array($event->key, [R_KEY_UP, R_KEY_DOWN], true)) {
            $this->highlighted = true;
        }
        if ($this->highlighted || ! in_array($event->key, [R_KEY_ENTER, R_KEY_KP_ENTER], true)) {
            return false;
        }

        $this->committed();

        return true;
    }

    private function isInMenu(int $widget): bool
    {
        for ($at = $widget; $at !== 0 && $at !== R_INVALID_ID && Rwidget_is_valid($at) === R_TRUE; $at = Rwidget_get_parent($at)) {
            if ($at === $this->menu) {
                return true;
            }
        }

        return false;
    }

    /**
     * Raven closed the menu: Esc, a click outside, or one of its items.
     */
    private function menuClosed(int $menu): void
    {
        if ($menu !== $this->menu) {
            return;
        }

        $this->forgetMenu();
        $this->completionsHidden();
    }

    private function closeMenu(): void
    {
        $menu = $this->menu;
        if ($menu === 0) {
            return;
        }

        $this->forgetMenu();
        if (Rwidget_is_valid($menu) === R_TRUE && Rpopup_is_open($menu) === R_TRUE) {
            Rpopup_close($menu, 0);
        }
    }

    private function forgetMenu(): void
    {
        $this->destroyRetired();
        $this->retired = $this->menu;
        $this->menu = 0;
        $this->highlighted = false;

        $window = $this->window();
        if ($window instanceof RavenWindow) {
            $window->unwatchInput($this->uuid());
        }
    }

    private function destroyRetired(): void
    {
        if ($this->retired !== 0 && Rwidget_is_valid($this->retired) === R_TRUE) {
            Rwidget_destroy($this->retired);
        }

        $this->retired = 0;
    }
}
