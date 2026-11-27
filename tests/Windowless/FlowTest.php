<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\Insets;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php and
| LabelTruncateTest.php. The flow is Raven's wrap box in a border: children at their own
| size, wrapping where the width is used up. Read back from Rwidget_get_rect().
*/

/** A flow of $count buttons in a window $width wide, laid out. @return array{object, object} */
function ravenFlowOfButtons(object $test, int $width, int $count = 6, int $spacing = 0, Insets|int $padding = 0): array
{
    $menus = ravenMenus();
    $test->opened[] = $window = ravenWindow($menus);
    Rhost_window_set_size($window->native(), $width, 400, 1.0);
    $flow = $window->column('m')->flow('tags', $spacing, $padding)->align(Align::FILL, Align::START);
    for ($i = 0; $i < $count; $i++) {
        $flow->button("b{$i}", "Tag number {$i}");
    }
    laidOut($window);
    laidOut($window);

    return [$window, $flow];
}

/** @return list<array{int, int, int, int}> x, y, width, height of each child, from the flow's own origin */
function ravenFrames(object $flow): array
{
    [$fx, $fy] = ravenRect($flow);

    return array_map(static function ($child) use ($fx, $fy): array {
        [$x, $y, $w, $h] = ravenRect($child);

        return [$x - $fx, $y - $fy, $w, $h];
    }, $flow->children());
}

it('puts the children side by side at their own size on one line when the width allows', function (): void {
    [, $flow] = ravenFlowOfButtons($this, 1400);

    $frames = ravenFrames($flow);
    expect(array_unique(array_column($frames, 1)))->toHaveCount(1)
        ->and($frames[0][0])->toBe(0);
    foreach ($frames as $i => $frame) {
        if ($i > 0) {
            expect($frame[0])->toBe($frames[$i - 1][0] + $frames[$i - 1][2]);
        }
    }
});

it('wraps onto a second line, from the left, where the width runs out', function (): void {
    [, $flow] = ravenFlowOfButtons($this, 300);

    $frames = ravenFrames($flow);
    $lines = array_values(array_unique(array_column($frames, 1)));
    [, , $width] = ravenRect($flow);
    expect(count($lines))->toBeGreaterThan(1);
    foreach ($frames as [$x, $y, $w]) {
        expect($x + $w)->toBeLessThanOrEqual($width + 1);
    }
    foreach ($lines as $y) {
        $first = array_values(array_filter($frames, fn (array $f): bool => $f[1] === $y))[0];
        expect($first[0])->toBe(0);
    }
});

it('keeps the gap on a line and between lines as spacing', function (): void {
    [, $flow] = ravenFlowOfButtons($this, 300, 6, 9);

    $frames = ravenFrames($flow);
    $first = array_values(array_filter($frames, fn (array $f): bool => $f[1] === $frames[0][1]));
    $lines = array_values(array_unique(array_column($frames, 1)));
    expect($first[1][0])->toBe($frames[0][0] + $frames[0][2] + 9)
        ->and($lines[1])->toBe($lines[0] + $frames[0][3] + 9);
});

it('keeps the padding round the lines', function (): void {
    [, $flow] = ravenFlowOfButtons($this, 300, 6, 0, Insets::of([5, 7, 11, 13]));

    $frames = ravenFrames($flow);
    [, , $width, $height] = ravenRect($flow);
    $last = end($frames);
    expect($frames[0][0])->toBe(13)
        ->and($frames[0][1])->toBe(5)
        ->and($height)->toBe($last[1] + $last[3] + 11);
    foreach ($frames as [$x, , $w]) {
        expect($x + $w)->toBeLessThanOrEqual($width - 7 + 1);
    }
});

it('grows its height with the lines', function (): void {
    [$window, $flow] = ravenFlowOfButtons($this, 1400);
    [, , , $oneLine] = ravenRect($flow);
    [, , , $buttonHeight] = ravenRect($flow->children()[0]);
    expect($oneLine)->toBe($buttonHeight);

    Rhost_window_set_size($window->native(), 300, 400, 1.0);
    laidOut($window);
    laidOut($window);
    [, , , $wrapped] = ravenRect($flow);
    expect($wrapped)->toBeGreaterThan($oneLine);

    Rhost_window_set_size($window->native(), 1400, 400, 1.0);
    laidOut($window);
    laidOut($window);
    [, , , $again] = ravenRect($flow);
    expect($again)->toBe($oneLine);
});

it('gives a hidden child no room, and the room back when it is shown', function (): void {
    [$window, $flow] = ravenFlowOfButtons($this, 300, 3);
    $before = ravenFrames($flow);

    $flow->children()[0]->hide();
    laidOut($window);
    $hidden = ravenFrames($flow);
    expect($hidden[1][0])->toBe(0)
        ->and($hidden[1][1])->toBe(0);

    $flow->children()[0]->show();
    laidOut($window);
    expect(ravenFrames($flow))->toBe($before);
});

it('places a child with a margin inside it, and the margin takes room', function (): void {
    [$window, $flow] = ravenFlowOfButtons($this, 1400, 2);
    $flow->children()[0]->setMargin(Insets::of([4, 6, 8, 10]));
    laidOut($window);

    $frames = ravenFrames($flow);
    expect($frames[0][0])->toBe(10)
        ->and($frames[0][1])->toBe(4)
        ->and($frames[1][0])->toBe(10 + $frames[0][2] + 6);
});

it('follows a reorder', function (): void {
    [$window, $flow] = ravenFlowOfButtons($this, 1400, 3);
    $first = $flow->children()[0];

    $first->moveTo(2);
    laidOut($window);

    $frames = ravenFrames($flow);
    expect(array_map(fn ($c): string => $c->name(), $flow->children()))->toBe(['b1', 'b2', 'b0'])
        ->and($frames[0][0])->toBe(0)
        ->and($frames[2][0])->toBeGreaterThan($frames[1][0]);
});

it('takes a child out and places the rest again', function (): void {
    [$window, $flow] = ravenFlowOfButtons($this, 1400, 3);

    $flow->children()[0]->remove();
    laidOut($window);

    expect(ravenFrames($flow)[0][0])->toBe(0)
        ->and(count($flow->children()))->toBe(2);
});
