<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\UI\Align;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php (one core, one
| connected Raven for the process). A canvas reads no slot padding, so a fixed keeps its
| children's margin itself: the frame moves in by the left and top margin, and the fixed
| reaches past the right and bottom margin.
*/

/** A native's laid-out rectangle: [x, y, width, height]. */
function ravenRect(object $primitive): array
{
    Rwidget_get_rect($primitive->native(), $x, $y, $width, $height);

    return [(int) round($x), (int) round($y), (int) round($width), (int) round($height)];
}

it('moves a child of a fixed in by its margin, reaches past it, and follows it live', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $fixed = $window->column('m')->fixed('fixed')->align(Align::START, Align::START);
    $held = $fixed->at(10, 20, 50, 40)->column('held')->setMargin('3 4 5 6');
    Rdraw_frame();

    [$fx, $fy, $fw, $fh] = ravenRect($fixed);
    [$x, $y, $w, $h] = ravenRect($held);

    expect([$x - $fx, $y - $fy, $w, $h])->toBe([16, 23, 50, 40])
        ->and([$fw, $fh])->toBe([10 + 6 + 50 + 4, 20 + 3 + 40 + 5]);

    $held->setMargin(0);
    Rdraw_frame();
    [$fx, $fy, $fw, $fh] = ravenRect($fixed);
    [$x, $y] = ravenRect($held);

    expect([$x - $fx, $y - $fy, $fw, $fh])->toBe([10, 20, 60, 60]);
});
