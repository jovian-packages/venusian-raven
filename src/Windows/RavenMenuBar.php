<?php

namespace Jovian\Toolkits\Raven\Windows;

use Jovian\Toolkits\Raven\RavenSession;
use Surface\Toolkits\Menus\MenuProfile;

/**
 * A window's menu bar: a Raven MenuBar widget at the top of the window. On macOS Raven shows it
 * in the system bar while the window is active, after Raven's application menu, and it takes
 * no room in the window; elsewhere it is drawn in the window.
 */
class RavenMenuBar extends RavenMenus
{
    protected int $bar;

    /**
     * @param array{name?: string|null, version?: string|null, copyright?: string|null} $identity
     */
    public function __construct(
        RavenSession $session,
        MenuProfile $profile,
        string $window,
        int $window_rid,
        protected readonly array $identity,
    ) {
        parent::__construct($session, $profile, $window, $window_rid);
        $this->bar = Rwidget_create(R_WIDGET_MENU_BAR);
        foreach ($profile->folders as $folder) {
            $menu = Rmenu_add_menu($this->bar, $this->states->label($folder->id));
            $this->nodes[$folder->id] = $menu;
            $this->folders[$folder->id] = true;
            $this->fill($menu, $folder->items);
        }
        $this->applyAll();
    }

    /** The MenuBar widget, for the window's host box. */
    public function widget(): int
    {
        return $this->bar;
    }

    protected function about(): void
    {
        $this->session->showAbout($this->identity, $this->window_rid);
    }

    public function setItemEnabled(string $item, bool $enabled): void
    {
        $this->states->set($item, $enabled);
        foreach ($this->states->subtree($item) as $id) {
            $this->apply($id);
        }
    }

    public function isItemEnabled(string $item): bool
    {
        return $this->states->isEnabled($item);
    }

    public function setToggle(string $item, bool $on): void
    {
        $native = $this->toggles[$item] ?? throw new \Surface\Contracts\Toolkits\ToolkitRuntimeException("No toggle item '{$item}' in menu profile '{$this->menu->name}'.");
        $this->states->setToggle($item, $on);
        Rmenu_item_set_checked($native, $on ? R_TRUE : R_FALSE);
    }

    public function isToggled(string $item): bool
    {
        isset($this->toggles[$item]) || throw new \Surface\Contracts\Toolkits\ToolkitRuntimeException("No toggle item '{$item}' in menu profile '{$this->menu->name}'.");

        return $this->states->toggle($item);
    }

    public function setRadio(string $group, string $item): void
    {
        $this->states->setRadio($group, $item);
        $this->showRadio($group);
    }

    public function radio(string $group): string
    {
        return $this->states->radio($group);
    }

    public function setItemLabel(string $item, string $label): void
    {
        $native = $this->node($item);
        $this->states->setLabel($item, $label);
        isset($this->folders[$item]) ? Rmenu_set_title($native, $label) : Rmenu_item_set_text($native, $label);
    }

    public function itemLabel(string $item): string
    {
        return $this->states->label($item);
    }
}
