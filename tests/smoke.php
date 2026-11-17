<?php
/*
 * Smoke check of every primitive kind, menus, a child window, an alert and removals, run against
 * an application that requires this package (topside-dev). Headless: no window opens.
 *
 *   TOOLKIT_BRIDGE_DEFAULT=raven RAVEN_HEADLESS=1 zhp tests/smoke.php /path/to/app
 *
 * Prints "ok N, failed M" and the failures.
 */
use Surface\Toolkits\Toolkits;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\Primitives\{Align, ListRow, CollectionItem, TableColumn, TreeNode, TKTransition};
use Surface\Contracts\Toolkits\Styling\{FontSpec, FontWeight, TextAlignment};
use Surface\Contracts\Toolkits\Services\Alert;
use Surface\Toolkits\Menus\MenuProfile;

chdir($argv[1]);
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/main.php';
$app->make(\Voyager\Contracts\Core\Kernel::class)->bootstrap();
$app['event-loop'];

$ok = 0; $bad = [];
$try = function (string $what, Closure $f) use (&$ok, &$bad) {
    try { $f(); $ok++; } catch (Throwable $e) { $bad[] = "$what: ".get_class($e).': '.$e->getMessage().' @'.basename($e->getFile()).':'.$e->getLine(); }
};
$frames = function (int $n = 3) { for ($i = 0; $i < $n; $i++) Rdraw_frame(); };

$bridge = Toolkits::bridge('raven');
$menu = MenuProfile::parse('smoke', [
    ['label' => 'App', 'items' => [['role' => 'about', 'label' => 'About'], ['separator' => true], ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q']]],
    ['label' => 'Edit', 'items' => [['role' => 'copy', 'label' => 'Copy'], ['role' => 'paste', 'label' => 'Paste']]],
    ['label' => 'View', 'items' => [
        ['id' => 'grid', 'label' => 'Show Grid', 'toggle' => true, 'on' => false],
        ['id' => 'view.refresh', 'label' => 'Refresh', 'keys' => 'Primary+Shift+F5'],
        ['id' => 'a', 'label' => 'A', 'radio' => 'g', 'on' => true], ['id' => 'b', 'label' => 'B', 'radio' => 'g'],
    ]],
]);
$w = $bridge->open('smoke', 900, 700, $menu);
$try('menu toggle', function () use ($w) { $w->setToggle('grid', true); assert($w->isToggled('grid')); $w->setRadio('g', 'b'); if ($w->radio('g') !== 'b') throw new Exception('radio'); $w->setItemEnabled('view.refresh', false); $w->setItemLabel('view.refresh', 'Reload'); if ($w->itemLabel('view.refresh') !== 'Reload') throw new Exception('label'); });
$col = $w->column('root', 8, 12);
$col->fill();
$p = [];
$try('label', function () use ($col, &$p) { $p['label'] = $col->label('label', 'Hello')->setFont(new FontSpec(18, FontWeight::BOLD))->setTextColor(Color::rgba(255, 0, 0, 1.0))->setAlignment(TextAlignment::RIGHT)->setWrap(true); });
$try('link', fn () => $col->link('link', 'site', 'https://example.com')->setText('site two'));
$try('button', function () use ($col) { $b = $col->button('button', 'Press'); $b->setLabel('Pressed'); $b->setBackground(Color::rgba(0, 0, 255, 0.5)); $b->setCornerRadius(6); $b->setAlignment(TextAlignment::LEFT); });
$try('image', fn () => $col->image('image', '/nonexistent.png'));
$try('separator', fn () => $col->separator('sep'));
$try('spinner', fn () => $col->spinner('spin')->start());
$try('progress', function () use ($col) { $col->progressBar('p1', 0.4)->setFraction(0.6); $col->progressBar('p2', null); });
$try('textInput', fn () => $col->textInput('ti', 'a', 'type', false)->setValue('b'));
$try('textArea', function () use ($col) { $t = $col->textArea('ta', "one\ntwo\nthree"); $t->selectLines(0, 1); $t->setWrap(true)->setReadOnly(true); $t->scrollToLine(2); });
$try('checkbox', fn () => $col->checkbox('cb', 'Check', true)->setChecked(false));
$try('toggle', fn () => $col->toggle('tg', true)->setOn(false));
$try('toggleButton', fn () => $col->toggleButton('tb', 'Bold', false)->setPressed(true));
$try('slider', fn () => $col->slider('sl', 0, 10, 3)->setValue(5));
$try('dropdown', fn () => $col->dropdown('dd', ['a', 'b', 'c'], 1)->select(2));
$try('combo', fn () => $col->comboBox('combo', 'x', ['one', 'two'])->setValue('two'));
$try('menuButton', fn () => $col->menuButton('mb', 'More', [['id' => 'm1', 'label' => 'One'], ['id' => 't1', 'label' => 'T', 'toggle' => true]]));
$try('datepicker', fn () => $col->datepicker('dp', new DateTimeImmutable('2026-01-02')));
$try('segmented', fn () => $col->segmentedControl('seg', ['L', 'M', 'R'], 1)->select(2));
$try('table', fn () => $col->table('tbl', [new TableColumn('n', 'Name'), new TableColumn('a', 'Age')], [['n' => 'Ada', 'a' => 36], ['n' => 'Bob|X', 'a' => 1]])->selectRow(1));
$try('tree', function () use ($col) { $t = $col->tree('tree', [new TableColumn('n', 'Name'), new TableColumn('s', 'Size')], [new TreeNode('r', ['n' => 'root', 's' => '1k'], [new TreeNode('c', ['n' => 'child'])])]); $t->expand('r'); $t->selectNode('c'); });
$try('list', function () use ($col) { $l = $col->listView('list', [new ListRow(null, 'Title', 'detail', 'NEW', [], 'Go'), new ListRow(null, 'Off', enabled: false)]); $l->insertItems(1, [new ListRow(null, 'Mid')]); $l->removeItems(0); $l->scrollPosition(); });
$try('collection', fn () => $col->collectionView('coll', [new CollectionItem(null, 'cap'), new CollectionItem(null, 'cap2')])->setCellSize(120, 100));
$try('row', function () use ($col) { $r = $col->row('row', 4, 2); $r->label('a', 'A'); $r->label('b', 'B')->fill(true, false)->align(Align::CENTER, Align::CENTER); $r->view('a')->moveTo(1); });
$try('grid', function () use ($col) { $g = $col->grid('grid', 2, 2); $g->at(0, 0)->label('a', 'A'); $g->at(1, 1, 1, 2)->button('b', 'B')->fill(); });
$try('fixed', function () use ($col) { $f = $col->fixed('fx'); $f->at(10, 10, 50, 20)->label('a', 'A'); $f->move($f->view('a'), 20, 20); });
$try('scroll', function () use ($col) { $s = $col->scrollView('sv'); $s->setScrollbars(false, true); $s->column('content')->label('x', 'X'); });
$try('split', function () use ($col) { $s = $col->split('split', true); $s->column('left')->label('l', 'L'); $s->column('right')->label('r', 'R'); $s->setPositions([0.3]); });
$try('tabs', function () use ($col) { $t = $col->tabs('tabs'); $t->addPage('one', 'One')->label('x', 'X'); $t->addPage('two', 'Two', 0); $t->select('one'); $t->setTitle('one', 'Uno'); if ($t->selected() !== 'one') throw new Exception('selected '.$t->selected()); });
foreach (['video' => fn () => $col->video('vd'), 'web' => fn () => $col->webView('wv'), 'scrollbar' => fn () => $col->scrollBar('sb')] as $k => $f) {
    try { $f(); $bad[] = "$k: no exception"; } catch (\Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException $e) { $ok++; } catch (Throwable $e) { $bad[] = "$k: ".get_class($e).': '.$e->getMessage(); }
}
$frames(4);
$try('present', fn () => $w->present());
$try('canvas', function () use ($col, $frames) {
    $cv = $col->canvas('cv')->minSize(32, 16);
    $frames(4);
    [$pw, $ph] = $cv->pixelSize();
    if ($pw < 32 || $ph < 16 || $cv->pixelScale() <= 0) throw new Exception("canvas is {$pw}x{$ph} at scale ".$cv->pixelScale());
    $cv->display(str_repeat("\xff\x00\x00\xff", 8 * 4), 8, 4);   // a new texture
    $cv->display(str_repeat("\x00\xff\x00\xff", 8 * 4), 8, 4);   // the same size: an update
    $cv->display(str_repeat("\x00\x00\xff\xff", $pw * $ph), $pw, $ph);   // another size: a new texture
    // a frame of twice the size, as a display of twice the density gives: the canvas keeps its size
    $cv->display(str_repeat("\x10\x20\x30\xff", $pw * 2 * $ph * 2), $pw * 2, $ph * 2);
    $frames(4);
    if ($cv->pixelSize() !== [$pw, $ph]) throw new Exception('a larger frame resized the canvas to '.implode('x', $cv->pixelSize())." from {$pw}x{$ph}");
    try { $cv->display('abc', 8, 4); throw new Exception('short bytes were taken'); } catch (\Surface\Contracts\Toolkits\ToolkitRuntimeException) {}
    $frames(2);
});
$try('styling', function () use ($col) { $col->setBackground(Color::rgba(10, 10, 10, 1.0))->setOpacity(0.9)->minSize(200, 200); $col->view('label')->watchSize(); $col->view('label')->appear(TKTransition::FADE)->setVisible(false); $col->view('label')->setVisible(true); });
$frames(6);
$try('size', function () use ($w, $col) { [$a, $b] = $w->size(); [$c, $d] = $col->size(); if ($a <= 0 || $c <= 0) throw new Exception("sizes $a $b $c $d"); });
$try('focus', function () use ($col, $w) { $col->view('ti')->focus(); if ($w->focused() !== $col->view('ti')) throw new Exception('focused '.var_export($w->focused()?->path(), true)); });
$try('popup', fn () => $col->view('button')->popupMenu([['id' => 'x', 'label' => 'X']], 1, 1));
$try('child', function () use ($bridge, $w) { $c = $bridge->open('child', 300, 200, null, $w); $c->column('root')->label('l', 'child'); $c->present(); Rdraw_frame(); $c->close(); if ($bridge->has('child')) throw new Exception('child still open'); });
$try('alert', function () use ($bridge, $w) { $id = $bridge->dialogs()->alert($w, new Alert('Sure?', 'Really', ['Yes', 'No'], 0, 1)); Rdraw_frame(); $bridge->dialogs()->closeOn($w); });
$try('clipboard', fn () => $bridge->clipboard()->hasText());
$try('accent', fn () => $bridge->systemAccent()->color());
$try("remove", function () use ($col) { foreach (['tbl', 'tree', 'list', 'tabs', 'split', 'grid', 'button', 'tb', 'mb'] as $n) { $v = $col->window()->view("root.$n") ?? throw new Exception("no view $n; children: ".implode(",", array_map(fn ($c) => $c->path(), $col->children()))); $v->remove(); } });
$frames(3);
$try('session pump', fn () => $bridge->session()->pump(20_000_000));
$try('close', function () use ($w, $bridge) { $w->close(); if ($bridge->has('smoke')) throw new Exception('still open'); });
echo "ok $ok, failed ", count($bad), "\n", implode("\n", $bad), "\n";
exit(0);
