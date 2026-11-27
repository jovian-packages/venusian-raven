<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitSession;
use Surface\Contracts\Toolkits\Mail\Views\ViewResized;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php (one core, one
| connected Raven for the process). Raven reports no widget resize: a view that watches its
| size is registered with its window, and RavenWindow::settle() asks it after every pump.
| ViewResized is held under the view's key until the loop flushes it.
*/

/** The ViewResized mail held for the loop, taken out. @return list<ViewResized> */
function viewResizes(array $menus): array
{
    [, , $session] = $menus;

    return Closure::bind(function (): array {
        [$held, $this->latest] = [$this->latest, []];

        return array_values(array_filter($held, fn (object $m): bool => $m instanceof ViewResized));
    }, $session, ToolkitSession::class)();
}

it('posts ViewResized with the size a watched view is laid out at, and again at a new size', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $row = $window->column('m')->row('strip')->fill()->watchSize();
    laidOut($window);
    viewResizes($menus);

    Rhost_window_set_size($window->native(), 900, 400, 2.0);
    laidOut($window);
    $mail = viewResizes($menus);

    expect($mail)->toHaveCount(1)
        ->and([$mail[0]->width, $mail[0]->height])->toBe($row->size())
        ->and($mail[0]->width)->toBeGreaterThan(300)
        ->and($mail[0]->path)->toBe($row->path());
});

it('posts nothing for a size it has already posted', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $window->column('m')->row('strip')->fill()->watchSize();
    laidOut($window);
    viewResizes($menus);

    laidOut($window);

    expect(viewResizes($menus))->toBe([]);
});

it('posts nothing after the view stops watching', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $row = $window->column('m')->row('strip')->fill()->watchSize();
    laidOut($window);
    $row->watchSize(false);
    viewResizes($menus);

    Rhost_window_set_size($window->native(), 1000, 500, 2.0);
    laidOut($window);

    expect(viewResizes($menus))->toBe([]);
});

it('posts again once it is turned back on', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $row = $window->column('m')->row('strip')->fill()->watchSize();
    laidOut($window);
    $row->watchSize(false);
    $row->watchSize();
    viewResizes($menus);

    Rhost_window_set_size($window->native(), 700, 300, 2.0);
    laidOut($window);

    expect(viewResizes($menus))->toHaveCount(1);
});
