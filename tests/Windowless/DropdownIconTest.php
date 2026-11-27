<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php, LabelTruncateTest.php
| and SaveTest.php. Raven's combo box draws no icon on its items, so with icons the dropdown is a
| Raven button opening a Raven menu; a press goes through Raven's in-memory host.
*/

/** @return array{object, object, array} */
function ravenKindDropdown(object $test): array
{
    $menus = ravenMenus();
    $test->opened[] = $window = ravenWindow($menus);
    $dropdown = $window->column('m')->dropdown('kind', ['All', 'Images', 'Videos', 'Audio'], 1);
    laidOut($window);
    delivered($menus);

    return [$window, $dropdown, $menus];
}

/**
 * What a Raven widget holds, depth first: 'image' for each image, a label's text.
 *
 * @return list<string>
 */
function ravenShown(int $widget): array
{
    $found = [];
    for ($i = 0, $count = Rwidget_get_child_count($widget); $i < $count; $i++) {
        $child = Rwidget_get_child($widget, $i);
        $type = Rwidget_get_type($child);
        if ($type === R_WIDGET_IMAGE) {
            $found[] = 'image';
        } elseif ($type === R_WIDGET_LABEL) {
            $found[] = 'label:'.Rlabel_get_text($child);
        }
        array_push($found, ...ravenShown($child));
    }

    return $found;
}

/** @return list<object> what the session holds for the loop, taken out */
function ravenOutbox(array $menus): array
{
    return Closure::bind(function (): array {
        [$mail, $this->outbox] = [$this->outbox, []];

        return $mail;
    }, $menus[2], Surface\Toolkits\ToolkitSession::class)();
}

/** @return array{int, int, int} the combo box, the button (0 before there is one) and the menu */
function ravenFaces(object $dropdown): array
{
    return (fn (): array => [$this->combo, $this->button, $this->menu])->call($dropdown);
}

it('stays Raven\'s combo box with no icons, and turns into a button with the selected icon, its label and the chevron once there are', function (): void {
    [$window, $dropdown] = ravenKindDropdown($this);
    [$combo] = ravenFaces($dropdown);
    $before = $dropdown->control();

    $dropdown->setIcons([['symbol' => 'search'], ['symbol' => 'image']]);
    laidOut($window);
    [, $button] = ravenFaces($dropdown);

    expect($before)->toBe($combo)
        ->and($dropdown->control())->toBe($button)
        ->and(Rwidget_get_child($dropdown->native(), 0))->toBe($button)
        ->and(ravenShown($button))->toBe(['image', 'label:Images', 'image']);
});

it('shows the selected icon and the chevron alone on a flat button once icon-only', function (): void {
    [$window, $dropdown] = ravenKindDropdown($this);
    $dropdown->setIconOnly();
    $dropdown->setIcons([null, ['symbol' => 'image']]);
    laidOut($window);
    [, $button] = ravenFaces($dropdown);

    expect(ravenShown($button))->toBe(['image', 'image']);

    $dropdown->setIconOnly(false);
    $dropdown->setIcons([]);
    laidOut($window);

    expect($dropdown->control())->toBe(ravenFaces($dropdown)[0]);
});

it('opens a menu of the options under the button, the selected one checked, and posts the one pressed', function (): void {
    [$window, $dropdown, $menus] = ravenKindDropdown($this);
    $dropdown->setIcons([['symbol' => 'search'], ['symbol' => 'image']]);
    laidOut($window);
    [$combo, $button] = ravenFaces($dropdown);

    ravenClick($window, $button);
    laidOut($window);
    [, , $menu] = ravenFaces($dropdown);
    $items = array_map(fn (int $i): int => Rwidget_get_child($menu, $i), range(0, Rwidget_get_child_count($menu) - 1));
    Rwidget_get_rect($button, $bx, $by, $bw, $bh);
    Rwidget_get_rect($menu, $mx, $my, $mw, $mh);

    expect(array_map(fn (int $item): string => (string) Rmenu_item_get_text($item), $items))->toBe(['All', 'Images', 'Videos', 'Audio'])
        ->and(array_map(fn (int $item): int => Rmenu_item_is_checked($item), $items))->toBe([R_FALSE, R_TRUE, R_FALSE, R_FALSE])
        ->and(Rpopup_is_open($menu))->toBe(R_TRUE)
        ->and([$mx, $my])->toBe([$bx, $by + $bh + 2.0]);

    // A menu draws its rows itself (an item has no rect of its own): the middle of the third of four.
    $scale = Rwindow_get_scale($window->native());
    [$atX, $atY] = [(int) round(($mx + $mw / 2) * $scale), (int) round(($my + $mh * 2.5 / 4) * $scale)];
    Rhost_inject_mouse_move($window->native(), $atX, $atY, 0);
    foreach ([R_TRUE, R_FALSE] as $pressed) {
        Rhost_inject_mouse_button($window->native(), $atX, $atY, R_MOUSE_LEFT, $pressed, 0);
    }
    laidOut($window);
    $mail = array_values(array_filter(ravenOutbox($menus), fn (object $m): bool => $m instanceof SelectionChanged));

    expect($mail)->toHaveCount(1)
        ->and($mail[0]->index)->toBe(2)
        ->and($mail[0]->option)->toBe('Videos')
        ->and($dropdown->selected())->toBe(2)
        ->and(Rcombo_box_get_selected($combo))->toBe(2)
        ->and(ravenShown($button))->toBe(['label:Videos', 'image']);
});
