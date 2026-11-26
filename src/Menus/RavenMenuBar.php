<?php

namespace Jovian\Toolkits\Raven\Menus;

use WeakReference;
use RuntimeException;
use Jovian\Toolkits\Raven\RavenWindow;
use Jovian\Toolkits\Raven\RavenSession;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Runtime\ToolkitMenuBar;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Menus\BarNode;
use Surface\Contracts\Toolkits\Menus\BarNodeKind;
use Surface\Contracts\Toolkits\Menus\StandardKind;

/**
 * A Raven MenuBar per window, first in the window's box. Raven shows it: inside the window on
 * Linux, and on macOS in the system bar while the window is the active one, after Raven's own
 * application menu. Raven gives the system bar the whole tree again whenever it changes.
 *
 * The bar for no window is built and never shown: with no window active, Raven shows its own
 * application menu only.
 *
 * The macOS application menu is Raven's: About, Hide, Hide Others, Show All and Quit. Its Quit
 * runs app.quit, which the session answers with the shown bar's Quit. Hide, Hide Others and
 * Show All are Raven's. Any other node of the application's own menu, About included, goes in
 * an ordinary menu under the application's name, after Raven's; it is collapsed while empty.
 *
 * Raven gives a key to the focused widget first and to the menus' shortcuts after it. On
 * macOS a shortcut Raven gives the system bar is AppKit's, which takes it first; so a
 * shortcut that yields to the focus has its text shown instead, and Raven still runs it.
 */
class RavenMenuBar extends ToolkitMenuBar
{
    /** The key of each edit kind, as Raven's text widgets take it with Primary; and Shift. */
    public const array EDIT = [
        'undo' => ['Z', false],
        'redo' => ['Z', true],
        'cut' => ['X', false],
        'copy' => ['C', false],
        'paste' => ['V', false],
        // Raven's text edit pastes plain text only: the same key as Paste.
        'paste-and-match-style' => ['V', false],
        'select-all' => ['A', false],
    ];

    /** The bar of the window Raven shows, of all the bars. */
    private static ?WeakReference $shown = null;

    protected int $bar;

    /** @var array<string, RavenMenuNode> */
    protected array $nodes = [];

    /** @var list<string> the bar's menus, in order */
    protected array $top = [];

    protected readonly bool $mac;

    public function __construct(RavenSession $session, ?ToolkitPoweredWindow $window = null, ?bool $mac = null)
    {
        parent::__construct($session, $window);

        $this->mac = $mac ?? device_os_family() === 'mac';
        $this->bar = Rwidget_create(R_WIDGET_MENU_BAR);

        if ($window instanceof RavenWindow) {
            $window->attachMenuBar($this->bar);
        }
    }

    public function widget(): int
    {
        return $this->bar;
    }

    /**
     * The widgets of one node.
     *
     * @return list<int>
     */
    public function natives(string $uuid): array
    {
        return $this->node($uuid)->natives;
    }

    public function isShown(): bool
    {
        return self::$shown?->get() === $this;
    }

    /**
     * Raven's own Quit ran: the shown bar answers it with its Quit. False when it has none.
     */
    public static function quitRequested(): bool
    {
        $bar = self::$shown?->get();
        foreach ($bar?->nodes ?? [] as $uuid => $node) {
            if ($node->quits) {
                $bar->chose((string) $uuid);

                return true;
            }
        }

        return false;
    }

    public function insert(?string $parent, int $index, BarNode $node): void
    {
        $into = $this->widgetOf($parent);
        $made = new RavenMenuNode($node, $parent);
        $this->nodes[$node->uuid] = $made;

        match ($node->kind) {
            BarNodeKind::SEPARATOR => $this->makeSeparator($made, $into),
            BarNodeKind::MENU => $this->makeMenu($made, $node, $into),
            BarNodeKind::CHOICE => $this->makeChoice($made, $node, $into),
            BarNodeKind::STANDARD => $this->makeStandard($made, $node, $into),
            BarNodeKind::ITEM, BarNodeKind::TOGGLE => $this->makeItem($made, $node, $into),
        };

        // Raven appends: each native is moved to its place.
        foreach ($made->natives as $native) {
            Rwidget_remove_child($into, $native);
        }
        $siblings = &$this->siblings($parent);
        array_splice($siblings, $index, 0, [$node->uuid]);
        unset($siblings);
        $this->place($made);

        $this->applyMarks($made);
        $this->applyEnabled($made, $node->enabled);
        $this->applyShortcut($made);
        $this->collapseApplicationMenu();
    }

    public function remove(string $uuid): void
    {
        $node = $this->node($uuid);

        $siblings = &$this->siblings($node->parent);
        array_splice($siblings, (int) array_search($uuid, $siblings, true), 1);
        unset($siblings);

        foreach ($node->natives as $native) {
            Rwidget_destroy($native);    // and everything under it
        }
        $this->forget($uuid);
        $this->collapseApplicationMenu();
    }

    public function move(string $uuid, ?string $parent, int $index): void
    {
        $node = $this->node($uuid);
        $from = $this->widgetOf($node->parent);
        foreach ($node->natives as $native) {
            Rwidget_remove_child($from, $native);
        }

        $siblings = &$this->siblings($node->parent);
        array_splice($siblings, (int) array_search($uuid, $siblings, true), 1);
        unset($siblings);

        $node->parent = $parent;
        $to = &$this->siblings($parent);
        array_splice($to, $index, 0, [$uuid]);
        unset($to);

        $this->place($node);
        $this->collapseApplicationMenu();
    }

    public function change(string $uuid, array $changes): void
    {
        $node = $this->node($uuid);

        if (array_key_exists('label', $changes) && $node->natives !== []) {
            match ($node->kind) {
                BarNodeKind::MENU => Rmenu_set_title($node->natives[0], $changes['label']),
                BarNodeKind::ITEM, BarNodeKind::TOGGLE, BarNodeKind::STANDARD => Rmenu_item_set_text($node->natives[0], $changes['label']),
                default => null,
            };
        }
        if (array_key_exists('enabled', $changes)) {
            $this->applyEnabled($node, $changes['enabled']);
        }
        if (array_key_exists('on', $changes) || array_key_exists('chosen', $changes)) {
            $node->on = array_key_exists('on', $changes) ? $changes['on'] : $node->on;
            $node->chosen = array_key_exists('chosen', $changes) ? $changes['chosen'] : $node->chosen;
            $this->applyMarks($node);
        }
        if (array_key_exists('shortcut', $changes)) {
            $node->shortcut = $changes['shortcut'];
            $this->applyShortcut($node);
        }
    }

    /**
     * Raven shows the active window's bar itself. This bar answers Raven's own Quit from now.
     */
    public function show(): void
    {
        self::$shown = WeakReference::create($this);
    }

    public function release(): void
    {
        if ($this->isShown()) {
            self::$shown = null;
        }

        // The window destroys its widgets with it; one still open lets the bar go.
        if (! $this->window instanceof RavenWindow || $this->window->isOpen()) {
            Rwidget_destroy($this->bar);
        }

        [$this->nodes, $this->top] = [[], []];
    }

    /**
     * Raven has no call that makes a window full screen.
     */
    public function supports(StandardKind $kind): bool
    {
        return $kind !== StandardKind::FULLSCREEN;
    }

    /**
     * A node was chosen. Raven has flipped a checkable item already: the marks go back to what
     * topside last gave, and topside changes them through change().
     *
     * @internal
     */
    public function chose(string $uuid, ?string $option = null): void
    {
        if (is_null($node = $this->nodes[$uuid] ?? null) || $node->inert) {
            return;
        }

        if ($node->kind === BarNodeKind::TOGGLE || $node->kind === BarNodeKind::CHOICE) {
            $this->applyMarks($node);
            $this->activated($uuid, $node->kind === BarNodeKind::TOGGLE ? ! $node->on : $option);

            return;
        }

        if ($node->kind === BarNodeKind::STANDARD && ! $node->replaced && $this->does($node)) {
            return;
        }

        $this->activated($uuid);
    }

    /**
     * What Raven does by itself for a standard item. False when it is the application's to answer.
     */
    protected function does(RavenMenuNode $node): bool
    {
        $window = $this->window instanceof RavenWindow && $this->window->isOpen() ? $this->window->native() : null;

        if ($node->standard->isEdit()) {
            // Raven's text widgets edit by their keys: the focused one gets them.
            [$key, $shift] = self::EDIT[$node->standard->value];
            $modifiers = ($this->mac ? R_MOD_SUPER : R_MOD_CTRL) | ($shift ? R_MOD_SHIFT : 0);
            if (! is_null($window)) {
                Rwindow_inject_key($window, constant("R_KEY_{$key}"), R_TRUE, $modifiers);
                Rwindow_inject_key($window, constant("R_KEY_{$key}"), R_FALSE, $modifiers);
            }

            return true;
        }

        if ($node->standard === StandardKind::CLOSE_WINDOW) {
            // Raven's close runs the window's close callback and its confirm, as the user's does.
            if (! is_null($window)) {
                Rwindow_close($window);
            }

            return true;
        }

        if ($node->standard === StandardKind::MINIMIZE) {
            if (! is_null($window)) {
                Rwindow_minimize($window);
            }

            return true;
        }

        if ($node->standard === StandardKind::ABOUT && ! is_null($node->panel)) {
            if (! is_null($window)) {
                $this->raven()->showAbout($node->panel, $window);
            }

            return true;
        }

        return false;
    }

    protected function makeSeparator(RavenMenuNode $made, int $into): void
    {
        if ($into !== $this->bar) {
            $made->natives = [Rmenu_add_separator($into)];
        }
    }

    protected function makeMenu(RavenMenuNode $made, BarNode $node, int $into): void
    {
        $made->natives = [Rmenu_add_menu($into, $node->label)];
    }

    protected function makeChoice(RavenMenuNode $made, BarNode $node, int $into): void
    {
        foreach ($node->options as $value => $label) {
            $native = Rmenu_add_item($into, (string) $label);
            Rmenu_item_set_checkable($native, R_TRUE);
            $this->onClick($native, $node->uuid, (string) $value);
            $made->natives[] = $native;
            $made->options[] = (string) $value;
        }
    }

    protected function makeStandard(RavenMenuNode $made, BarNode $node, int $into): void
    {
        if ($this->merges($made->parent)) {
            if ($node->standard === StandardKind::QUIT) {
                $made->quits = true;    // Raven's own Quit, through app.quit

                return;
            }
            if (! $node->replaced && $node->standard->isMacOnly()) {
                return;    // Raven's own Hide, Hide Others and Show All
            }
        }

        if (! $node->replaced && $node->standard->isMacOnly()) {
            $made->inert = true;    // Raven has no call for it anywhere else
        }

        $this->makeItem($made, $node, $into);
    }

    protected function makeItem(RavenMenuNode $made, BarNode $node, int $into): void
    {
        $native = Rmenu_add_item($into, $node->label);
        if ($node->kind === BarNodeKind::TOGGLE) {
            Rmenu_item_set_checkable($native, R_TRUE);
        }
        $this->onClick($native, $node->uuid);
        $made->natives = [$native];
    }

    /**
     * The callback holds the bar weakly: the bar holds the item.
     */
    protected function onClick(int $native, string $uuid, ?string $option = null): void
    {
        $bar = WeakReference::create($this);
        Rmenu_item_set_clicked_callback($native, static function () use ($bar, $uuid, $option): void {
            $bar->get()?->chose($uuid, $option);
        }, null);
    }

    /**
     * Whether what is put under $parent goes to Raven's macOS application menu.
     */
    protected function merges(?string $parent): bool
    {
        return $this->mac && ! is_null($parent) && $this->node($parent)->application;
    }

    protected function applyEnabled(RavenMenuNode $node, bool $enabled): void
    {
        foreach ($node->natives as $native) {
            Rwidget_set_enabled($native, $enabled && ! $node->inert ? R_TRUE : R_FALSE);
        }
    }

    protected function applyMarks(RavenMenuNode $node): void
    {
        foreach ($node->natives as $at => $native) {
            match ($node->kind) {
                BarNodeKind::TOGGLE => Rmenu_item_set_checked($native, $node->on === true ? R_TRUE : R_FALSE),
                BarNodeKind::CHOICE => Rmenu_item_set_checked($native, ! is_null($node->chosen) && $node->options[$at] === $node->chosen ? R_TRUE : R_FALSE),
                default => null,
            };
        }
    }

    protected function applyShortcut(RavenMenuNode $node): void
    {
        if ($node->natives === [] || in_array($node->kind, [BarNodeKind::MENU, BarNodeKind::CHOICE, BarNodeKind::SEPARATOR], true)) {
            return;
        }

        $native = $node->natives[0];
        $shortcut = $node->shortcut;
        $edit = $node->kind === BarNodeKind::STANDARD && ! $node->replaced && $node->standard->isEdit();

        if (is_null($shortcut)) {
            Rmenu_item_set_shortcut($native, R_KEY_UNKNOWN, 0);
            Rmenu_item_set_shortcut_text($native, null);

            return;
        }

        // An edit item's keys are the focused widget's own: shown, and never bound.
        Rmenu_item_set_shortcut($native, $edit ? R_KEY_UNKNOWN : RavenShortcut::key($shortcut->key), $edit ? 0 : RavenShortcut::modifiers($shortcut, $this->mac));

        // Shown as text where a key the system bar has would be AppKit's first.
        $text = $edit || ($this->mac && $shortcut->yieldsToFocus());
        Rmenu_item_set_shortcut_text($native, $text ? RavenShortcut::text($shortcut, $this->mac) : null);
    }

    /**
     * Put a node's widgets in its menu at its place: after the widgets of the siblings before it.
     */
    protected function place(RavenMenuNode $node): void
    {
        $into = $this->widgetOf($node->parent);
        $self = array_search($node, $this->nodes, true);
        $at = 0;
        foreach ($this->siblings($node->parent) as $uuid) {
            if ($uuid === $self) {
                break;
            }
            $at += count($this->nodes[$uuid]->natives);
        }

        foreach ($node->natives as $native) {
            Rwidget_insert_child($into, $native, $at++);
        }
    }

    /**
     * macOS: the application's own menu is collapsed while it holds nothing of its own.
     */
    protected function collapseApplicationMenu(): void
    {
        if (! $this->mac) {
            return;
        }

        foreach ($this->top as $uuid) {
            $node = $this->nodes[$uuid];
            if (! $node->application || $node->natives === []) {
                continue;
            }

            $empty = true;
            foreach ($node->children as $child) {
                $sibling = $this->nodes[$child];
                $empty = $empty && ($sibling->natives === [] || $sibling->kind === BarNodeKind::SEPARATOR);
            }
            Rwidget_set_visibility($node->natives[0], $empty ? R_COLLAPSED : R_VISIBLE);
        }
    }

    protected function forget(string $uuid): void
    {
        $node = $this->node($uuid);
        foreach ($node->children as $child) {
            $this->forget($child);
        }
        unset($this->nodes[$uuid]);
    }

    /**
     * @return list<string>
     */
    protected function &siblings(?string $parent): array
    {
        if (is_null($parent)) {
            return $this->top;
        }

        $node = $this->node($parent);

        return $node->children;
    }

    /**
     * The bar, or the menu widget a node is.
     */
    protected function widgetOf(?string $parent): int
    {
        if (is_null($parent)) {
            return $this->bar;
        }

        $node = $this->node($parent);
        if ($node->kind !== BarNodeKind::MENU) {
            throw new RuntimeException("Menu node '{$parent}' is not a menu: nothing goes inside it.");
        }

        return $node->natives[0];
    }

    protected function node(string $uuid): RavenMenuNode
    {
        return $this->nodes[$uuid] ?? throw new RuntimeException("This bar has no menu node '{$uuid}'.");
    }

    protected function raven(): RavenSession
    {
        /** @var RavenSession */
        return $this->session;
    }
}
