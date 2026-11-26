<?php

declare(strict_types=1);

use Jovian\Toolkits\Raven\Menus\RavenMenuBar;
use Jovian\Toolkits\Raven\Menus\RavenShortcut;
use Jovian\Toolkits\Raven\Providers\VenusianRavenServiceProvider;
use Jovian\Toolkits\Raven\RavenAdapterDriver;
use Jovian\Toolkits\Raven\RavenSession;
use Jovian\Toolkits\Raven\RavenWindow;
use Surface\Core\Providers\TopsideServiceProvider;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Toolkits\Menus\About;
use Surface\Toolkits\Menus\AboutPanel;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\Standard;
use Surface\Toolkits\Menus\Toggle;
use Surface\Toolkits\Menus\Runtime\MenuNodeActivated;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\ToolkitSession;
use Surface\Contracts\Toolkits\Menus\BarNode;
use Surface\Contracts\Toolkits\Menus\BarNodeKind;
use Surface\Contracts\Toolkits\Menus\ShortcutKey;
use Surface\Contracts\Toolkits\Menus\StandardKind;
use Voyager\Config\Repository;
use Voyager\Core\RenderedInstance;
use Voyager\Vessel\ControlPanel;

/*
| The menu bar on real Raven widgets, on Raven's host platform with the null renderer: Raven
| opens no OS window, and keys are handed to a window with Rhost_inject_key as an OS would.
| The host platform has no system bar, so a bar is drawn in its window, as on Linux. What needs
| the macOS system bar (Raven giving it the tree, AppKit's key equivalents) is checked by running
| an app.
|
|   zhp …/apps/topside-dev/vendor/bin/pest --bootstrap=…/apps/topside-dev/vendor/autoload.php tests/Windowless
*/

const MAC = PHP_OS_FAMILY === 'Darwin';

if (! extension_loaded('raven')) {
    throw new RuntimeException('venusian-raven menu tests need ext-raven loaded.');
}

/** One core and one connected Raven for the process, on the host platform. */
function ravenApp(?MenuBar $default = null): RenderedInstance
{
    static $registered = false;
    if (! $registered) {
        Rhost_platform_register(new RHostPlatformDesc());
        $registered = true;
    }

    $windows = is_null($default) ? [] : ['default_menu' => 'app', 'menus' => ['app' => $default]];
    $app = new RenderedInstance(sys_get_temp_dir());
    $app->registerInstance('config', new Repository([
        'app' => ['name' => 'My App'],
        'toolkits' => ['default' => 'raven', 'windows' => $windows],
        'raven' => ['application_name' => 'My App', 'backend' => 'null', 'headless' => false, 'ui_scale' => 0.0, 'theme' => null, 'wait_slice_ms' => 8.0],
    ]));
    $app->register(TopsideServiceProvider::class);
    $app->register(VenusianRavenServiceProvider::class);
    $app->boot();
    ControlPanel::setInstance($app);

    return $app;
}

/** @return array{MenuManager, RavenAdapterDriver, RavenSession} */
function ravenMenus(?MenuBar $default = null): array
{
    static $session = null;
    $app = ravenApp($default);

    $session ??= ToolkitAdapters::driver()->connect();
    $driver = new RavenAdapterDriver($app);
    Closure::bind(function () use ($session): void {
        $this->session = $session;
    }, $driver, RavenAdapterDriver::class)();

    return [$app->make(MenuManager::class), $driver, $session];
}

function ravenWindow(array $menus, ?MenuBar $bar = null, string $name = 'main'): RavenWindow
{
    [$manager, $driver] = $menus;
    /** @var RavenWindow $window */
    $window = $driver->open($name, 300, 200);
    $manager->opened($window, $driver, $bar);
    $window->present();
    Rhost_window_set_size($window->native(), 600, 400, 2.0);
    activate($window);

    return $window;
}

/** The host platform gives the window the keyboard, and the session settles. */
function activate(RavenWindow $window): void
{
    Rhost_inject_focus($window->native(), R_TRUE);
    Rdraw_frame();
    $window->settle();
}

function deactivate(RavenWindow $window): void
{
    Rhost_inject_focus($window->native(), R_FALSE);
    Rdraw_frame();
    $window->settle();
}

function barOf(string $window = 'main'): RavenMenuBar
{
    return (fn () => $this->windows[$window]->mounter->bar)->call(app(MenuManager::class));
}

/**
 * What a Raven menu holds, as Raven has it: an item's text, '-' a line, a menu as '>' and its
 * own contents; a collapsed menu as '(…)'.
 *
 * @return list<string|array>
 */
function treeOf(int $widget): array
{
    $out = [];
    for ($i = 0, $count = Rwidget_get_child_count($widget); $i < $count; $i++) {
        $child = Rwidget_get_child($widget, $i);
        if (Rwidget_get_type($child) === R_WIDGET_MENU) {
            $inner = treeOf($child);
            $out[] = Rwidget_get_visibility($child) === R_COLLAPSED ? ['(collapsed)' => $inner] : ['>' => $inner];
            continue;
        }
        $text = (string) Rmenu_item_get_text($child);
        $out[] = $text === '' ? '-' : $text;
    }

    return $out;
}

/** The item with this text in a Raven menu. */
function itemIn(int $menu, string $text): int
{
    for ($i = 0, $count = Rwidget_get_child_count($menu); $i < $count; $i++) {
        $child = Rwidget_get_child($menu, $i);
        if (Rwidget_get_type($child) !== R_WIDGET_MENU && Rmenu_item_get_text($child) === $text) {
            return $child;
        }
    }

    throw new RuntimeException("No menu item '{$text}'.");
}

/** A top-level menu of a bar, counted from the end: the application's may come first. */
function lastMenu(RavenMenuBar $bar, int $back = 1): int
{
    return Rwidget_get_child($bar->widget(), Rwidget_get_child_count($bar->widget()) - $back);
}

/** @return list<object> what was posted, each node chosen delivered as the loop would */
function delivered(array $menus): array
{
    [$manager, , $session] = $menus;
    $take = fn (): array => Closure::bind(function (): array {
        [$mail, $this->outbox] = [$this->outbox, []];

        return $mail;
    }, $session, ToolkitSession::class)();

    foreach ($take() as $mail) {
        if ($mail instanceof MenuNodeActivated) {
            $manager->deliver($mail);
        }
    }

    return $take();
}

/** @return list<string> */
function names(array $mail): array
{
    return array_map(fn (object $mail): string => $mail->name(), $mail);
}

function press(RavenWindow $window, int $key, int $modifiers = 0): void
{
    Rhost_inject_key($window->native(), $key, R_TRUE, $modifiers, R_FALSE);
    Rhost_inject_key($window->native(), $key, R_FALSE, $modifiers, R_FALSE);
    Rdraw_frame();
}

function primary(): int
{
    return MAC ? R_MOD_SUPER : R_MOD_CTRL;
}

afterEach(function (): void {
    foreach ($this->opened ?? [] as $window) {
        if ($window->isOpen()) {
            $window->close();
        }
    }
    Rdraw_frame();
});

it('builds the tree in the order written, first in its window', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus, MenuBar::make(
        Menu::make('File', 'file',
            Item::make('Open', id: 'open'),
            Separator::make(),
            Menu::make('Export', 'export', Item::make('PDF', id: 'pdf')),
            Choice::make(['s' => 'Small', 'l' => 'Large'], 'l', id: 'size'),
            Toggle::make('Wrap', true, id: 'wrap'),
        ),
        Menu::make('Help', 'help', Item::make('Guide', id: 'guide')),
    ));
    $bar = barOf();
    $tree = treeOf($bar->widget());

    expect(Rwidget_get_child(Rwindow_get_content($window->native()), 0))->toBe($bar->widget())
        ->and(array_slice($tree, -2))->toBe([
            ['>' => ['Open', '-', ['>' => ['PDF']], 'Small', 'Large', 'Wrap']],
            ['>' => ['Guide']],
        ])
        ->and(Rmenu_item_is_checked(itemIn(lastMenu($bar, 2), 'Small')))->toBe(R_FALSE)
        ->and(Rmenu_item_is_checked(itemIn(lastMenu($bar, 2), 'Large')))->toBe(R_TRUE)
        ->and(Rmenu_item_is_checked(itemIn(lastMenu($bar, 2), 'Wrap')))->toBe(R_TRUE)
        ->and(Rmenu_bar_is_native($bar->widget()))->toBe(R_FALSE);    // the host platform has no system bar
});

it('posts an item\'s mail, after its callback', function (): void {
    $menus = ravenMenus();
    $ran = 0;
    $this->opened[] = ravenWindow($menus, MenuBar::make(Menu::make('File', 'file',
        Item::make('Save', id: 'save', onClick: function () use (&$ran): void { $ran++; }),
    )));
    delivered($menus);

    $uuid = array_key_first((fn () => array_filter($this->nodes, fn ($n) => $n->kind === BarNodeKind::ITEM))->call(barOf()));
    barOf()->chose($uuid);
    expect($ran)->toBe(0);    // never inside Raven's callback

    $mail = delivered($menus);
    expect($ran)->toBe(1)->and($mail[0]->name())->toBe('menu.clicked.file.save')->and($mail[0]->window)->toBe('main');
});

it('runs a shortcut with a modifier in the window, and flips nothing topside has not', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus, MenuBar::make(Menu::make('View', 'view',
        Item::make('Save', id: 'save')->shortcut(Shortcut::primary('S')),
        Toggle::make('Wrap', false, id: 'wrap')->shortcut(Shortcut::primary('W')->shift()),
        Choice::make(['1' => 'One', '2' => 'Two'], '1', id: 'zoom'),
    )));
    delivered($menus);
    $bar = barOf();

    press($window, R_KEY_S, primary());
    press($window, R_KEY_W, primary() | R_MOD_SHIFT);

    // Raven flipped the toggle before its callback; the bar put back topside's, and topside set it on.
    expect(Rmenu_item_is_checked(itemIn(lastMenu($bar), 'Wrap')))->toBe(R_TRUE)
        ->and(names(delivered($menus)))->toBe(['menu.clicked.view.save', 'menu.toggled.view.wrap']);

    $two = (fn () => array_values(array_filter($this->nodes, fn ($n) => $n->kind === BarNodeKind::CHOICE)))->call($bar)[0];
    $uuid = (fn () => array_search($two, $this->nodes, true))->call($bar);
    $bar->chose($uuid, '2');
    expect(Rmenu_item_is_checked($two->natives[0]))->toBe(R_FALSE)
        ->and(Rmenu_item_is_checked($two->natives[1]))->toBe(R_TRUE)
        ->and(delivered($menus)[0]->option)->toBe('2');
});

it('gives a bare key to the focused text input first, and to the menu when nothing uses it', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus, MenuBar::make(Menu::make('Format', 'format',
        Item::make('Remove Last Line', id: 'trim')->shortcut(Shortcut::key('Delete')),
    )));
    $input = Rwidget_create(R_WIDGET_TEXT_INPUT);
    Rwidget_add_child(Rwindow_get_content($window->native()), $input);
    Rtext_input_set_text($input, 'abc');
    Rdraw_frame();
    delivered($menus);

    Rwidget_focus($input);
    press($window, R_KEY_HOME);
    press($window, R_KEY_DELETE);
    expect(Rtext_input_get_text($input))->toBe('bc')
        ->and(delivered($menus))->toBe([]);

    press($window, R_KEY_ESCAPE);    // Raven drops the focus
    press($window, R_KEY_DELETE);
    expect(Rtext_input_get_text($input))->toBe('bc')
        ->and(names(delivered($menus)))->toBe(['menu.clicked.format.trim']);
});

it('shows an edit item\'s keys and leaves them to the focused widget', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus, MenuBar::make(Menu::make('Edit', 'edit', Standard::selectAll(), Standard::cut())));
    $input = Rwidget_create(R_WIDGET_TEXT_INPUT);
    Rwidget_add_child(Rwindow_get_content($window->native()), $input);
    Rtext_input_set_text($input, 'one two');
    Rdraw_frame();
    Rwidget_focus($input);
    delivered($menus);
    $bar = barOf();

    // the menu's items send the keys to the window, and the input does the work
    $nodes = (fn () => array_keys($this->nodes))->call($bar);
    foreach (array_slice($nodes, 1) as $uuid) {
        $bar->chose($uuid);
    }
    Rdraw_frame();

    expect(Rtext_input_get_text($input))->toBe('')
        ->and(delivered($menus))->toBe([]);
});

it('gives Paste and Match Style the key of Paste, and has a key for every edit kind', function (): void {
    foreach (StandardKind::cases() as $kind) {
        if ($kind->isEdit()) {
            expect(RavenMenuBar::EDIT)->toHaveKey($kind->value);
        }
    }

    expect(RavenMenuBar::EDIT[StandardKind::PASTE_AND_MATCH_STYLE->value])
        ->toBe(RavenMenuBar::EDIT[StandardKind::PASTE->value]);
});

it('asks to quit and quits nothing', function (): void {
    $menus = ravenMenus();
    $this->opened[] = ravenWindow($menus, MenuBar::make(Menu::make('Program', 'program', Standard::quit())));
    delivered($menus);

    $uuid = (fn () => array_key_last($this->nodes))->call(barOf());
    barOf()->chose($uuid);

    expect(names(delivered($menus)))->toBe(['quit.requested']);
});

it('answers Raven\'s own Quit with the shown bar\'s, on macOS', function (): void {
    [, , $session] = ravenMenus();
    $bar = new RavenMenuBar($session, null, true);
    $bar->insert(null, 0, new BarNode(BarNodeKind::MENU, 'app', 'My App', application: true));
    $bar->insert('app', 0, new BarNode(BarNodeKind::STANDARD, 'quit', 'Quit My App', standard: StandardKind::QUIT, replaced: true));

    $chosen = [];
    $spy = new class($session, null, true) extends RavenMenuBar {
        public array $log = [];
        protected function activated(string $uuid, bool|string|null $value = null): void { $this->log[] = $uuid; }
    };
    $spy->insert(null, 0, new BarNode(BarNodeKind::MENU, 'app', 'My App', application: true));
    $spy->insert('app', 0, new BarNode(BarNodeKind::STANDARD, 'quit', 'Quit My App', standard: StandardKind::QUIT));
    $spy->show();

    expect(RavenMenuBar::quitRequested())->toBeTrue()
        ->and($spy->log)->toBe(['quit'])
        ->and($spy->natives('quit'))->toBe([]);    // Raven's Quit is the item

    $spy->release();
    expect(RavenMenuBar::quitRequested())->toBeFalse();    // the session asks the application then
    $bar->release();
});

it('leaves Raven\'s macOS application menu Raven\'s, and the rest under the application\'s name', function (): void {
    [, , $session] = ravenMenus();
    $bar = new RavenMenuBar($session, null, true);
    $bar->insert(null, 0, new BarNode(BarNodeKind::MENU, 'app', 'My App', application: true));
    $bar->insert('app', 0, new BarNode(BarNodeKind::STANDARD, 'hide', 'Hide', standard: StandardKind::HIDE));
    $bar->insert('app', 1, new BarNode(BarNodeKind::STANDARD, 'quit', 'Quit', standard: StandardKind::QUIT));
    $bar->insert(null, 1, new BarNode(BarNodeKind::MENU, 'file', 'File'));
    $bar->insert('file', 0, new BarNode(BarNodeKind::ITEM, 'save', 'Save'));

    expect(treeOf($bar->widget()))->toBe([['(collapsed)' => []], ['>' => ['Save']]]);

    $bar->insert('app', 0, new BarNode(BarNodeKind::STANDARD, 'about', 'About My App', standard: StandardKind::ABOUT, panel: new AboutPanel('My App')));
    $bar->insert('app', 1, new BarNode(BarNodeKind::ITEM, 'updates', 'Check for Updates'));
    expect(treeOf($bar->widget()))->toBe([['>' => ['About My App', 'Check for Updates']], ['>' => ['Save']]]);

    $bar->remove('about');
    $bar->remove('updates');
    expect(treeOf($bar->widget())[0])->toBe(['(collapsed)' => []]);

    $bar->release();
});

it('makes the application menu an ordinary first menu off macOS', function (): void {
    [, , $session] = ravenMenus();
    $bar = new RavenMenuBar($session, null, false);
    $bar->insert(null, 0, new BarNode(BarNodeKind::MENU, 'app', 'My App', application: true));
    $bar->insert('app', 0, new BarNode(BarNodeKind::STANDARD, 'about', 'About', standard: StandardKind::ABOUT, replaced: true));
    $bar->insert('app', 1, new BarNode(BarNodeKind::STANDARD, 'quit', 'Quit', standard: StandardKind::QUIT));

    expect(treeOf($bar->widget()))->toBe([['>' => ['About', 'Quit']]])
        ->and(count($bar->natives('quit')))->toBe(1);

    $bar->release();
});

it('closes its window from Close Window, by the window\'s own close path', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus, MenuBar::make(Menu::make('Window', 'window', Standard::closeWindow())));

    barOf()->chose((fn () => array_key_last($this->nodes))->call(barOf()));

    expect($window->isOpen())->toBeFalse();
});

it('shows what Raven cannot do disabled', function (): void {
    $menus = ravenMenus();
    $this->opened[] = ravenWindow($menus, MenuBar::make(Menu::make('Window', 'window',
        Standard::minimize(), Standard::fullscreen(), Standard::closeWindow(),
    )));
    $bar = barOf();
    $items = (fn () => array_values(array_filter($this->nodes, fn ($n) => $n->kind === BarNodeKind::STANDARD)))->call($bar);

    expect(array_map(fn ($node): int => Rwidget_is_enabled($node->natives[0]), $items))->toBe([R_TRUE, R_FALSE, R_TRUE])
        ->and($bar->supports(StandardKind::FULLSCREEN))->toBeFalse()
        ->and($bar->supports(StandardKind::MINIMIZE))->toBeTrue();
});

it('shows enabled and disabled, menus inside menus too', function (): void {
    $menus = ravenMenus();
    $bar = fn (bool $on): MenuBar => MenuBar::make(
        Menu::make('File', 'file',
            Item::make('Save', id: 'save')->enabled($on),
            Menu::make('Export', 'export', Item::make('PDF', id: 'pdf'))->enabled($on),
        ),
        Menu::make('Tools', 'tools', Item::make('Run', id: 'run'))->enabled($on),
    );
    $this->opened[] = ravenWindow($menus, $bar(false));
    $native = barOf();
    // on macOS the empty application menu topside puts first is not counted
    $state = fn (): array => (fn () => array_map(fn ($n): int => Rwidget_is_enabled($n->natives[0]), array_values(array_filter($this->nodes, fn ($n) => ! $n->application))))->call($native);

    // File, Save, Export, PDF, Tools, Run: File itself stays enabled
    expect($state())->toBe([R_TRUE, R_FALSE, R_FALSE, R_FALSE, R_FALSE, R_FALSE]);

    $menus[0]->window('main', $bar(true));
    expect($state())->toBe([R_TRUE, R_TRUE, R_TRUE, R_TRUE, R_TRUE, R_TRUE]);

    $menus[0]->window('main', $bar(false));
    expect($state())->toBe([R_TRUE, R_FALSE, R_FALSE, R_FALSE, R_FALSE, R_FALSE]);
});

it('changes in place: label, order, what is added and removed, shortcut', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus, MenuBar::make(Menu::make('List', 'list',
        Item::make('One', id: 'one'),
        Item::make('Two', id: 'two'),
        Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'letter'),
        Item::make('Three', id: 'three'),
    )));
    $bar = barOf();
    $list = Rwidget_get_child($bar->widget(), Rwidget_get_child_count($bar->widget()) - 1);
    $kept = Rwidget_get_child($list, 0);
    $gone = Rwidget_get_child($list, 1);

    $menus[0]->window('main', MenuBar::make(Menu::make('List', 'list',
        Choice::make(['a' => 'A', 'b' => 'B'], 'a', id: 'letter'),
        Item::make('New', id: 'new'),
        Item::make('Three', id: 'three'),
        Item::make('First', id: 'one')->shortcut(Shortcut::primary('J')),
    )));
    Rdraw_frame();
    delivered($menus);

    expect(treeOf($list))->toBe(['A', 'B', 'New', 'Three', 'First'])
        ->and(Rwidget_get_child($list, 4))->toBe($kept)
        ->and(Rwidget_is_valid($gone))->toBe(R_FALSE);

    press($window, R_KEY_J, primary());
    expect(names(delivered($menus)))->toBe(['menu.clicked.list.one']);
});

it('maps every key topside has to a Raven key', function (): void {
    foreach (ShortcutKey::cases() as $key) {
        expect(RavenShortcut::key($key))->not->toBe(R_KEY_UNKNOWN, $key->value);
    }

    expect(RavenShortcut::modifiers(Shortcut::of('primary+shift+s'), true))->toBe(R_MOD_SUPER | R_MOD_SHIFT)
        ->and(RavenShortcut::modifiers(Shortcut::of('primary+shift+s'), false))->toBe(R_MOD_CTRL | R_MOD_SHIFT)
        ->and(RavenShortcut::modifiers(Shortcut::of('ctrl+alt+up'), true))->toBe(R_MOD_CTRL | R_MOD_ALT)
        ->and(RavenShortcut::text(Shortcut::of('delete'), true))->toBe('⌦')
        ->and(RavenShortcut::text(Shortcut::of('shift+f5'), true))->toBe('⇧F5')
        ->and(RavenShortcut::text(Shortcut::of('primary+c'), true))->toBe('⌘C')
        ->and(RavenShortcut::text(Shortcut::of('primary+c'), false))->toBe('Ctrl+C');
});

it('opens the About dialog with the fields given, one at a time', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $session = $menus[2];

    $session->showAbout(new AboutPanel('My App', '1.2', '2026 Me', 'Thanks'), $window->native());
    $content = $session->aboutDialog();
    $session->showAbout(new AboutPanel('Other'), $window->native());

    $labels = [];
    for ($i = 0; $i < Rwidget_get_child_count($content); $i++) {
        $child = Rwidget_get_child($content, $i);
        if (Rwidget_get_type($child) === R_WIDGET_LABEL) {
            $labels[] = Rlabel_get_text($child);
        }
    }
    expect($content)->not->toBeNull()
        ->and($session->aboutDialog())->toBe($content)
        ->and($labels)->toBe(['My App 1.2', '2026 Me', 'Thanks']);

    Rdialog_close($content, 1);
    Rdraw_frame();
    expect($session->aboutDialog())->toBeNull();
});
