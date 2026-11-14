<?php

namespace Jovian\Toolkits\Raven\Windows;

use Jovian\Toolkits\Raven\RavenSession;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\Toolkits\Mail\Menus\MenuActivated;
use Surface\Contracts\Toolkits\Mail\Menus\MenuRadioChosen;
use Surface\Contracts\Toolkits\Mail\Menus\MenuToggled;
use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Surface\Contracts\Toolkits\Menus\MenuRole;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Toolkits\Menus\Accelerator;
use Surface\Toolkits\Menus\ContextMenu;
use Surface\Toolkits\Menus\MenuItem;
use Surface\Toolkits\Menus\MenuProfile;
use Surface\Toolkits\Menus\MenuStates;
use WeakReference;

/**
 * A menu tree built into Raven menus: items, separators, folders, toggles (checkable items),
 * radio groups (checkable items kept exclusive here) and shortcuts. Every item posts mail. Edit
 * roles have no Raven action: they send their key chord to the window's focused widget, and
 * carry no Raven shortcut of their own (the chord reaches the widget directly).
 */
abstract class RavenMenus
{
    /** The chord of each edit role, as Raven's text widgets take it. */
    public const array EDIT = [
        'undo' => [R_KEY_Z, false],
        'redo' => [R_KEY_Z, true],
        'cut' => [R_KEY_X, false],
        'copy' => [R_KEY_C, false],
        'paste' => [R_KEY_V, false],
        'select-all' => [R_KEY_A, false],
    ];

    /** @var array<string, int> every node, item or folder, by id */
    protected array $nodes = [];

    /** @var array<string, bool> folders by id */
    protected array $folders = [];

    /** @var array<string, int> toggle items by id */
    protected array $toggles = [];

    /** @var array<string, array<string, int>> radio items by group, then id */
    protected array $groups = [];

    protected readonly MenuStates $states;

    public function __construct(
        protected readonly RavenSession $session,
        protected readonly MenuProfile|ContextMenu $menu,
        protected readonly string $window,
        protected readonly int $window_rid,
    ) {
        $this->states = new MenuStates($menu);
    }

    /** The About role: the app's About box. */
    abstract protected function about(): void;

    /** A toggle or radio change the owner keeps (a primitive's stored context menu). */
    protected function kept(string $item, bool $on, ?string $group): void {}

    /** @param list<MenuItem> $items */
    protected function fill(int $menu, array $items): void
    {
        foreach ($items as $item) {
            if ($item->separator) {
                Rmenu_add_separator($menu);
            } elseif ($item->isFolder()) {
                $folder = Rmenu_add_menu($menu, $this->states->label($item->id));
                $this->nodes[$item->id] = $folder;
                $this->folders[$item->id] = true;
                $this->fill($folder, $item->items);
            } else {
                $this->leaf($menu, $item);
            }
        }
    }

    protected function leaf(int $menu, MenuItem $item): void
    {
        $native = Rmenu_add_item($menu, $this->states->label($item->id));
        $this->nodes[$item->id] = $native;
        if (! is_null($item->keys) && $item->role?->isEdit() !== true) {
            [$key, $modifiers] = self::shortcut($item->keys);
            if ($key !== R_KEY_UNKNOWN) {
                Rmenu_item_set_shortcut($native, $key, $modifiers);
            }
        }
        if ($item->toggle) {
            Rmenu_item_set_checkable($native, R_TRUE);
            Rmenu_item_set_checked($native, $this->states->toggle($item->id) ? R_TRUE : R_FALSE);
            $this->toggles[$item->id] = $native;
        }
        if (! is_null($item->radio())) {
            Rmenu_item_set_checkable($native, R_TRUE);
            $this->groups[$item->radio()][$item->id] = $native;
        }
        $self = WeakReference::create($this);
        Rmenu_item_set_clicked_callback($native, static fn () => $self->get()?->clicked($item), null);
    }

    public function clicked(MenuItem $item): void
    {
        if (! $this->states->effective($item->id)) {
            return;
        }
        if ($item->role?->isEdit() === true) {
            [$key, $shift] = self::EDIT[$item->role->value];
            $primary = PHP_OS_FAMILY === 'Darwin' ? R_MOD_SUPER : R_MOD_CTRL;
            $modifiers = $primary | ($shift ? R_MOD_SHIFT : 0);
            Rwindow_inject_key($this->window_rid, $key, R_TRUE, $modifiers);
            Rwindow_inject_key($this->window_rid, $key, R_FALSE, $modifiers);

            return;
        }
        match (true) {
            $item->role === MenuRole::ABOUT => $this->about(),
            $item->role === MenuRole::QUIT => $this->session->post(new QuitRequested($this->window === '' ? null : $this->window)),
            $item->toggle => $this->toggled($item),
            ! is_null($item->radio()) => $this->chosen($item),
            default => $this->session->post(new MenuActivated($this->window, $item->id)),
        };
    }

    protected function toggled(MenuItem $item): void
    {
        $on = ! $this->states->toggle($item->id);
        $this->states->setToggle($item->id, $on);
        Rmenu_item_set_checked($this->toggles[$item->id], $on ? R_TRUE : R_FALSE);
        $this->kept($item->id, $on, null);
        $this->session->post(new MenuToggled($this->window, $item->id, $on));
    }

    protected function chosen(MenuItem $item): void
    {
        $group = $item->radio();
        if ($this->states->radio($group) === $item->id) {
            $this->showRadio($group);

            return;
        }
        $this->states->setRadio($group, $item->id);
        $this->showRadio($group);
        $this->kept($item->id, true, $group);
        $this->session->post(new MenuRadioChosen($this->window, $group, $item->id));
    }

    /** Every item of $group shows off but the one on. */
    protected function showRadio(string $group): void
    {
        $on = $this->states->radio($group);
        foreach ($this->groups[$group] as $id => $native) {
            Rmenu_item_set_checked($native, $id === $on ? R_TRUE : R_FALSE);
        }
    }

    /** Show every node's effective state: a disabled folder disables what is under it. */
    protected function applyAll(): void
    {
        foreach (array_keys($this->groups) as $group) {
            $this->showRadio($group);
        }
        foreach (array_keys($this->nodes) as $id) {
            $this->apply($id);
        }
    }

    protected function apply(string $id): void
    {
        Rwidget_set_enabled($this->nodes[$id], $this->states->effective($id) ? R_TRUE : R_FALSE);
    }

    /** @throws ToolkitRuntimeException */
    protected function node(string $id): int
    {
        return $this->nodes[$id] ?? throw new ToolkitRuntimeException("No node '{$id}' in this menu.");
    }

    /**
     * An accelerator as Raven's key and modifiers. On macOS Raven's R_MOD_SUPER is Command.
     * @return array{int, int}
     */
    public static function shortcut(Accelerator $keys): array
    {
        $macos = PHP_OS_FAMILY === 'Darwin';
        $modifiers = ($keys->command($macos) || $keys->super($macos) ? R_MOD_SUPER : 0)
            | ($keys->control($macos) ? R_MOD_CTRL : 0)
            | ($keys->alt ? R_MOD_ALT : 0)
            | ($keys->shift ? R_MOD_SHIFT : 0);

        return [self::key($keys->key), $modifiers];
    }

    public static function key(Key $key): int
    {
        $name = match (true) {
            str_starts_with($key->value, 'digit_') => substr($key->value, 6),
            str_starts_with($key->value, 'numpad_') && ctype_digit(substr($key->value, 7)) => 'KP_'.substr($key->value, 7),
            $key->value === 'equals' => 'EQUAL',
            $key->value === 'left_meta' => 'LEFT_SUPER',
            $key->value === 'right_meta' => 'RIGHT_SUPER',
            default => strtoupper($key->value),
        };

        return defined("R_KEY_{$name}") ? constant("R_KEY_{$name}") : R_KEY_UNKNOWN;
    }
}
