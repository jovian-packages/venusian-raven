<?php

namespace Jovian\Toolkits\Raven\Windows;

use Jovian\Toolkits\Raven\RavenWindow;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Toolkits\Menus\ContextMenu;
use WeakReference;

/**
 * A context menu popped up at a point of a window (Rmenu_open). Raven reports no dismissal: the
 * menu widget is kept until the next popup of its primitive or the primitive's removal.
 */
class RavenContextMenu extends RavenMenus
{
    protected int $root;

    /** @var WeakReference<TKPrimitive>|null the primitive whose stored menu keeps the toggles and radios */
    protected ?WeakReference $owner;

    public function __construct(ContextMenu $menu, protected readonly RavenWindow $host, ?TKPrimitive $owner)
    {
        parent::__construct($host->session(), $menu, $host->name(), $host->rid());
        $this->owner = is_null($owner) ? null : WeakReference::create($owner);
        $this->root = Rwidget_create(R_WIDGET_MENU);
        $this->fill($this->root, $menu->items);
        $this->applyAll();
    }

    public function openAt(float $x, float $y): void
    {
        Rmenu_open($this->root, $this->window_rid, $x, $y);
    }

    public function isOpen(): bool
    {
        return Rwidget_is_valid($this->root) === R_TRUE && Rwidget_get_visibility($this->root) === R_VISIBLE;
    }

    public function close(): void
    {
        if (Rwidget_is_valid($this->root) === R_TRUE) {
            Rwidget_destroy($this->root);
        }
    }

    protected function kept(string $item, bool $on, ?string $group): void
    {
        $owner = $this->owner?->get();
        if (is_null($owner)) {
            return;
        }
        is_null($group) ? $owner->nativeContextToggled($item, $on) : $owner->nativeContextRadio($group, $item);
    }

    protected function about(): void
    {
        $this->session->showAbout([], $this->window_rid);
    }
}
