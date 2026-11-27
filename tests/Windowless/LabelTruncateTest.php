<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Styling\TextRun;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php (one core, one
| connected Raven for the process). Raven's label has no ellipsis: truncating, the adapter
| shows the text cut to the label's width with "…", asks for no width, and cuts again after a
| pump (RavenWindow::settle()) when the width changed.
*/

const TRUNCATE_LONG = 'A title far longer than the window it is shown in, so long that the window at its first size cannot hold all of it on one line';

/** A frame, the window's after-pump settle, and a frame for what that changed. */
function laidOut(object $window): void
{
    Rdraw_frame();
    $window->settle();
    Rdraw_frame();
}

/** A native's laid-out width. */
function ravenWidth(int $native): float
{
    Rwidget_get_rect($native, $x, $y, $width, $height);

    return $width;
}

it('cuts a long title with an ellipsis at the window\'s width and asks for none', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $column = $window->column('m');
    $label = $column->label('title', TRUNCATE_LONG)->setTruncate(true);
    laidOut($window);

    $control = (fn () => $this->control)->call($label);
    $shown = Rlabel_get_text($control);

    expect($shown)->toStartWith('A title')
        ->and($shown)->toEndWith('…')
        ->and(mb_strlen($shown))->toBeLessThan(mb_strlen(TRUNCATE_LONG))
        ->and(ravenWidth($label->native()))->toBeLessThanOrEqual(ravenWidth($column->native()))
        ->and($label->text())->toBe(TRUNCATE_LONG);
});

it('shows more when the window grows, the whole text when it fits, and it all again when it stops', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $label = $window->column('m')->label('title', TRUNCATE_LONG)->setWrap(true)->setTruncate(true);
    laidOut($window);
    $control = (fn () => $this->control)->call($label);
    $narrow = mb_strlen(Rlabel_get_text($control));

    Rhost_window_set_size($window->native(), 900, 400, 2.0);
    laidOut($window);
    expect(mb_strlen(Rlabel_get_text($control)))->toBeGreaterThan($narrow);

    Rhost_window_set_size($window->native(), 2400, 400, 2.0);
    laidOut($window);
    expect(Rlabel_get_text($control))->toBe(TRUNCATE_LONG);

    Rhost_window_set_size($window->native(), 600, 400, 2.0);
    $label->setTruncate(false);
    laidOut($window);
    expect(Rlabel_get_text($control))->toBe(TRUNCATE_LONG);
});

it('cuts runs run by run, as Markdown', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $label = $window->column('m')->label('title', '');
    $label->setRuns([new TextRun('Bold lead: ', bold: true), new TextRun(TRUNCATE_LONG)])->setTruncate(true);
    laidOut($window);

    $control = (fn () => $this->control)->call($label);
    expect(Rlabel_get_markdown($control))->toBe(R_TRUE)
        ->and(Rlabel_get_text($control))->toStartWith('**Bold lead')
        ->and(Rlabel_get_text($control))->toEndWith('…')
        ->and(Rlabel_get_text($control))->not->toContain('one line');
});

it('needs the cut: whole, the text is wider than the window', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $column = $window->column('m');
    $label = $column->label('title', TRUNCATE_LONG);
    laidOut($window);

    Rfont_measure_text(Rfont_get_default(), 0.0, TRUNCATE_LONG, $width, $height);
    expect($width)->toBeGreaterThan(ravenWidth($column->native()));
});
