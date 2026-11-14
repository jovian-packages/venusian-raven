<?php

namespace Jovian\Toolkits\Raven;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\RavenPrimitiveFactory;
use Jovian\Toolkits\Raven\Windows\RavenContextMenu;
use Jovian\Toolkits\Raven\Windows\RavenMenuBar;
use REvent;
use Surface\Contracts\Toolkits\Mail\Views\FocusGained;
use Surface\Contracts\Toolkits\Mail\Views\FocusLost;
use Surface\Contracts\Toolkits\Mail\Views\RightClicked;
use Surface\Contracts\Toolkits\Mail\Windows\WindowClosed;
use Surface\Contracts\Toolkits\Mail\Windows\WindowCloseRequested;
use Surface\Contracts\Toolkits\Mail\Windows\WindowFocused;
use Surface\Contracts\Toolkits\Mail\Windows\WindowResized;
use Surface\Contracts\Toolkits\Mail\Windows\WindowRightClicked;
use Surface\Contracts\Toolkits\Mail\Windows\WindowScaleChanged;
use Surface\Contracts\Toolkits\Menus\MenuProfile;
use Surface\Contracts\Toolkits\Primitives\PrimitiveFactory;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitWindow as WindowContract;
use Surface\Toolkits\Menus\ContextMenu;
use Surface\Toolkits\Menus\MenuProfile as ParsedMenuProfile;
use Surface\Toolkits\Windows\ChildWindows;
use Surface\Toolkits\Windows\ToolkitWindow;
use WeakReference;

/**
 * A top-level window is a Raven window (Rwindow_create, hidden until present()). Its content
 * widget is a vertical box holding the menu bar, when it has one, and the content container. A
 * child window (open() with a parent) is a Raven dialog inside the parent window: Raven dialogs
 * are modal to their window, and a child has no menu bar.
 */
class RavenWindow extends ToolkitWindow
{
    /** The Raven window; for a child, the parent's. 0 once closed. */
    protected int $rid;

    /** The box the content container goes in: the window's content widget, or the child's dialog content. */
    protected int $host;

    protected ?PrimitiveFactory $factory = null;

    /** @var WeakReference<TKPrimitive>|null focused on each present() */
    protected ?WeakReference $initial_focus = null;

    protected bool $confirming = false;

    /** close() runs: the close goes through while the window confirms close. */
    protected bool $closing = false;

    protected bool $presented = false;

    /** The scale last posted. */
    protected float $scale = 1.0;

    /** The focused widget's Rid as last seen, for FocusGained/FocusLost. */
    protected int $focus_rid = 0;

    /** Primitives by their native Rid, for focus lookups. @var array<int, WeakReference<TKPrimitive>> */
    protected array $by_rid = [];

    protected ?RavenMenuBar $menu_bar = null;

    /** The menu a right-click opened from a primitive's stored context menu. */
    protected ?RavenContextMenu $right_menu = null;

    public function __construct(
        protected readonly string $name,
        protected readonly RavenSession $session,
        protected readonly RavenBridge $driver,
        int $width,
        int $height,
        ?MenuProfile $menu,
        protected readonly ?WindowContract $parent = null,
    ) {
        $this->host = Rwidget_create(R_WIDGET_VBOX);
        $self = WeakReference::create($this);

        if ($parent instanceof self) {
            $this->rid = $parent->rid();
            Rwidget_set_min_size($this->host, $width, $height);
        } else {
            $this->rid = Rwindow_create($name, $width, $height, R_WINDOW_HIDDEN);
            if (Rwindow_is_valid($this->rid) !== R_TRUE) {
                Rerror_get_last($detail, $severity);
                throw new ToolkitRuntimeException("Raven did not open window '{$name}': ".($detail ?: 'Rwindow_create failed').'.');
            }
            Rwindow_set_content($this->rid, $this->host);
            $this->scale = Rwindow_get_scale($this->rid);

            // The user's close: refused while a child or dialog is open, asked about while confirming.
            Rwindow_set_close_callback($this->rid, static fn (): int => ($self->get()?->userCloses() ?? true) ? R_TRUE : R_FALSE, null);
            // A burst of resizes within one pump is one WindowResized, with the last size.
            Rwindow_set_resize_callback($this->rid, static fn (int $window, int $width, int $height) => $self->get()?->resized($width, $height), null);
            Rwindow_set_event_hook($this->rid, static function (int $window, REvent $event) use ($self): int {
                if ($event->type === R_EVENT_FOCUS_GAINED && $event->target === 0) {
                    $self->get()?->activated();
                }
                if ($event->type === R_EVENT_MOUSE_DOWN && $event->button === R_MOUSE_RIGHT) {
                    return ($self->get()?->rightClicked($event) ?? false) ? R_TRUE : R_FALSE;
                }

                return R_FALSE;
            }, null);
            if (! is_null($menu)) {
                $this->installMenuBar($menu);
            }
        }

        $session->afterPump("window.{$name}", static fn () => $self->get()?->afterPump());
    }

    public function name(): string
    {
        return $this->name;
    }

    /** The Raven window this window draws in (the parent's for a child). */
    public function rid(): int
    {
        return $this->rid ?: throw new ToolkitRuntimeException("Window '{$this->name}' is closed.");
    }

    public function title(): string
    {
        return $this->isChild() ? $this->name : (string) Rwindow_get_title($this->rid());
    }

    public function setTitle(string $title): static
    {
        if (! $this->isChild()) {
            Rwindow_set_title($this->rid(), $title);
        }

        return $this;
    }

    /** A child presents its parent first, then opens as a dialog over it. */
    public function present(): static
    {
        $this->rid();
        if ($this->isChild()) {
            if (! $this->parent->presented) {
                $this->parent->present();
            }
            if (! $this->presented) {
                $self = WeakReference::create($this);
                Rdialog_open($this->rid, $this->host, static fn () => $self->get()?->dialogClosed(), null);
            }
        } else {
            Rwindow_show($this->rid);
        }
        $this->presented = true;

        $initial = $this->initial_focus?->get();
        if (! is_null($initial) && ! $initial->isRemoved() && $initial instanceof RavenNative) {
            Rwidget_focus($initial->focusRid());
            $this->focus_rid = Rwindow_get_focused_widget($this->rid);
        }

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->rid !== 0;
    }

    public function isChild(): bool
    {
        return $this->parent instanceof self;
    }

    public function close(): void
    {
        if ($this->rid === 0) {
            return;
        }
        ChildWindows::childOf($this->driver, $this)?->close();
        $this->driver->dialogs()->closeOn($this);
        $this->closing = true;
        $rid = $this->rid;
        $host = $this->host;
        $this->closed();
        if ($this->isChild()) {
            if (Rwidget_is_valid($host) === R_TRUE && $this->presented) {
                Rdialog_close($host, -1);
            } elseif (Rwidget_is_valid($host) === R_TRUE) {
                Rwidget_destroy($host);
            }
        } else {
            Rwindow_destroy($rid);
        }
        $this->closing = false;
    }

    public function parent(): ?WindowContract
    {
        return $this->parent;
    }

    public function confirmClose(bool $on = true): static
    {
        $this->confirming = $on;

        return $this;
    }

    public function isConfirmingClose(): bool
    {
        return $this->confirming;
    }

    /** The primitive holding the window's focus: the one whose native holds Raven's focused widget. */
    public function focused(): ?TKPrimitive
    {
        if ($this->rid === 0) {
            return null;
        }

        return $this->primitiveAt(Rwindow_get_focused_widget($this->rid));
    }

    /**
     * @throws ToolkitRuntimeException When $primitive belongs to another window.
     */
    public function setInitialFocus(?TKPrimitive $primitive): static
    {
        if (! is_null($primitive) && $primitive->window() !== $this) {
            throw new ToolkitRuntimeException("'{$primitive->path()}' is not in window '{$this->name}'.");
        }
        $this->initial_focus = is_null($primitive) ? null : WeakReference::create($primitive);

        return $this;
    }

    /** A primitive's native, for focus lookups. */
    public function adopt(int $rid, TKPrimitive $primitive): void
    {
        $this->by_rid[$rid] = WeakReference::create($primitive);
    }

    public function release(int $rid): void
    {
        unset($this->by_rid[$rid]);
    }

    /** The primitive whose widget is $rid or holds it. */
    public function primitiveAt(int $rid): ?TKPrimitive
    {
        for ($at = $rid; $at !== 0 && $at !== R_INVALID_ID; $at = Rwidget_get_parent($at)) {
            $primitive = ($this->by_rid[$at] ?? null)?->get();
            if (! is_null($primitive) && ! $primitive->isRemoved()) {
                return $primitive;
            }
        }

        return null;
    }

    /** A child has no menu bar; a window's new bar replaces its old one. */
    public function setMenuBar(string $profile): static
    {
        $this->rid();
        ChildWindows::refuseMenuBar($this);
        $this->installMenuBar($this->driver->profile($profile));

        return $this;
    }

    public function setToggle(string $item, bool $on): static
    {
        $this->menuOrFail()->setToggle($item, $on);

        return $this;
    }

    public function isToggled(string $item): bool
    {
        return $this->menuOrFail()->isToggled($item);
    }

    public function setItemEnabled(string $item, bool $enabled): static
    {
        $this->menuOrFail()->setItemEnabled($item, $enabled);

        return $this;
    }

    public function isItemEnabled(string $item): bool
    {
        return $this->menuOrFail()->isItemEnabled($item);
    }

    public function setRadio(string $group, string $item): static
    {
        $this->menuOrFail()->setRadio($group, $item);

        return $this;
    }

    public function radio(string $group): string
    {
        return $this->menuOrFail()->radio($group);
    }

    public function setItemLabel(string $item, string $label): static
    {
        $this->menuOrFail()->setItemLabel($item, $label);

        return $this;
    }

    public function itemLabel(string $item): string
    {
        return $this->menuOrFail()->itemLabel($item);
    }

    public function menuBar(): ?RavenMenuBar
    {
        return $this->menu_bar;
    }

    /** The bar goes first in the host box, above the content. */
    protected function installMenuBar(MenuProfile $profile): void
    {
        if (! $profile instanceof ParsedMenuProfile) {
            throw new ToolkitRuntimeException(get_class($profile).' is not a menu profile parsed by Surface\\Toolkits\\Menus\\MenuProfile.');
        }
        $old = $this->menu_bar;
        $this->menu_bar = new RavenMenuBar($this->session, $profile, $this->name, $this->rid, $this->driver->about());
        Rwidget_insert_child($this->host, $this->menu_bar->widget(), 0);
        Rslot_set_halign($this->menu_bar->widget(), R_HALIGN_FILL);
        if (! is_null($old) && Rwidget_is_valid($old->widget()) === R_TRUE) {
            Rwidget_destroy($old->widget());
        }
    }

    protected function menuOrFail(): RavenMenuBar
    {
        return $this->menu_bar ?? throw new ToolkitRuntimeException("Window '{$this->name}' has no menu bar.");
    }

    /** Shown on screen: presented and not closed. */
    public function isShown(): bool
    {
        return $this->rid !== 0 && $this->presented;
    }

    /**
     * A right press: the innermost primitive under it gets RightClicked and opens its stored
     * context menu, if it has one; outside every primitive the window gets WindowRightClicked.
     * @return bool whether the press is consumed (a menu opened)
     */
    public function rightClicked(REvent $event): bool
    {
        $primitive = $this->primitiveAt($event->target);
        if (is_null($primitive)) {
            $this->session->post(new WindowRightClicked($this->name, $event->x, $event->y));

            return false;
        }
        Rwidget_get_rect($primitive->native(), $left, $top, $width, $height);
        $x = $event->x - $left;
        $y = $event->y - $top;
        $this->session->post(new RightClicked($this->name, $primitive->path(), $primitive->uuid(), $x, $y));
        $menu = $primitive->contextMenu();
        if (! $menu instanceof ContextMenu) {
            return false;
        }
        $this->right_menu?->close();
        $this->right_menu = new RavenContextMenu($menu, $this, $primitive);
        $this->right_menu->openAt($event->x, $event->y);

        return true;
    }

    /** The content area's size: the window's client size, or the child dialog content's. */
    public function size(): array
    {
        $this->rid();
        Rwidget_get_rect($this->host, $x, $y, $width, $height);

        return [(int) round($width), (int) round($height)];
    }

    /** The box the content container sits in. */
    public function host(): int
    {
        return $this->host;
    }

    public function factory(): PrimitiveFactory
    {
        return $this->factory ??= new RavenPrimitiveFactory($this);
    }

    public function session(): RavenSession
    {
        return $this->session;
    }

    /** Re-apply the content container's fill and align inside the host. */
    public function placeContent(TKPrimitiveGroup&RavenNative $content): void
    {
        [$horizontal, $vertical] = $content->fills();
        [$halign, $valign] = $content->ravenAlign();
        Rslot_set_halign($content->native(), $horizontal ? R_HALIGN_FILL : $halign);
        Rslot_set_valign($content->native(), $vertical ? R_VALIGN_FILL : $valign);
        if ($vertical || $valign === R_VALIGN_FILL) {
            Rslot_set_fill($content->native(), 1.0);
        } else {
            Rslot_set_auto($content->native());
        }
    }

    /**
     * @throws ToolkitRuntimeException When the container is not a raven primitive.
     */
    protected function mountContent(TKPrimitiveGroup $content): void
    {
        if (! $content instanceof RavenNative) {
            throw new ToolkitRuntimeException("'{$content->path()}' is not a raven primitive.");
        }
        Rwidget_add_child($this->host, $content->native());
        $this->placeContent($content);
    }

    /**
     * The user closed the window. A parent with an open child or dialog keeps it silently; a
     * confirming window posts WindowCloseRequested and stays.
     * @return bool whether Raven may close it
     */
    public function userCloses(): bool
    {
        if (! $this->closing && (! is_null(ChildWindows::childOf($this->driver, $this)) || ! is_null($this->driver->dialogs()->openOn($this)))) {
            return false;
        }
        if ($this->confirming && ! $this->closing) {
            $this->session->post(new WindowCloseRequested($this->name));

            return false;
        }
        $this->closed();

        return true;
    }

    /** A child's dialog closed from Raven's side (Escape): the window's close path. */
    public function dialogClosed(): void
    {
        if ($this->rid === 0 || $this->closing) {
            return;
        }
        if ($this->confirming) {
            // Raven has already closed the dialog and deleted its content: the window cannot stay.
            $this->session->post(new WindowCloseRequested($this->name));
        }
        $this->closed();
    }

    public function resized(int $width, int $height): void
    {
        $this->session->postLatest("window.resized.{$this->name}", new WindowResized($this->name, $width, $height));
        $scale = Rwindow_get_scale($this->rid);
        if ($scale !== $this->scale) {
            $this->scale = $scale;
            if ($this->presented) {
                $this->session->post(new WindowScaleChanged($this->name, $scale));
            }
        }
    }

    public function activated(): void
    {
        $this->driver->clipboard()->compare();
        $this->session->post(new WindowFocused($this->name));
    }

    /** Raven reports no focus change: the focused widget is compared after each pump. */
    public function afterPump(): void
    {
        if ($this->rid === 0 || $this->isChild()) {
            return;
        }
        $this->holdContent();
        $rid = Rwindow_get_focused_widget($this->rid);
        if ($rid === $this->focus_rid) {
            return;
        }
        $before = $this->primitiveAt($this->focus_rid);
        $after = $this->primitiveAt($rid);
        $this->focus_rid = $rid;
        if ($before === $after) {
            return;
        }
        if (! is_null($before)) {
            $this->session->post(new FocusLost($this->name, $before->path(), $before->uuid()));
        }
        if (! is_null($after)) {
            $this->session->post(new FocusGained($this->name, $after->path(), $after->uuid()));
        }
    }

    /** The content's size as last held: the window's floor, in points. */
    protected array $held = [0, 0];

    /**
     * A window is never smaller than what it holds, as AppKit's, GTK's and Qt's are not: Raven
     * draws content that does not fit over itself. Its floor follows the content's size, and a
     * window already smaller grows to it.
     */
    protected function holdContent(): void
    {
        Rwidget_get_desired_size($this->host, $width, $height);
        $floor = [(int) ceil($width ?? 0.0), (int) ceil($height ?? 0.0)];
        if ($floor === $this->held) {
            return;
        }
        $this->held = $floor;
        Rwindow_set_min_size($this->rid, ...$floor);
        Rwindow_get_size($this->rid, $w, $h);
        if ($w < $floor[0] || $h < $floor[1]) {
            Rwindow_set_size($this->rid, max($w, $floor[0]), max($h, $floor[1]));
        }
    }

    /**
     * The one close path: tear the content tree down while the native window still holds it,
     * drop the window's pending mail, post the close, let the driver forget the window.
     */
    protected function closed(): void
    {
        if ($this->rid === 0) {
            return;
        }
        $this->removeContent();
        $this->right_menu?->close();
        $this->right_menu = null;
        $this->rid = 0;
        $this->session->afterPump("window.{$this->name}", null);
        $this->session->forgetLatest("window.resized.{$this->name}");
        $this->session->post(new WindowClosed($this->name));
        $this->driver->forget($this->name);
    }
}
