<?php

declare(strict_types=1);

use Jovian\Toolkits\Raven\RavenWindow;
use Surface\Toolkits\ToolkitSession;
use Surface\Contracts\Toolkits\Dialogs\SaveRequest;
use Surface\Contracts\Toolkits\Mail\Windows\SaveAnswered;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php. Without a window
| system Raven shows its own file browser in a dialog over the window (Rfile_dialog_save),
| which waits for the answer in a loop of its own. The panel opens from the next frame; a timer
| that runs inside that loop reads it and answers it, as the user would: Esc, or a press on
| its Save button (a mouse press injected into the headless window).
*/

/** @return list<SaveAnswered> the answers the session holds, taken out */
function ravenSaveAnswers(array $menus): array
{
    [, , $session] = $menus;
    $mail = Closure::bind(function (): array {
        [$mail, $this->outbox] = [$this->outbox, []];

        return $mail;
    }, $session, ToolkitSession::class)();

    return array_values(array_filter($mail, fn (object $m): bool => $m instanceof SaveAnswered));
}

/** Every descendant of $widget of $type, depth first. @return list<int> */
function ravenDescendants(int $widget, int $type): array
{
    $found = [];
    for ($i = 0, $count = Rwidget_get_child_count($widget); $i < $count; $i++) {
        $child = Rwidget_get_child($widget, $i);
        if (Rwidget_get_type($child) === $type) {
            $found[] = $child;
        }
        array_push($found, ...ravenDescendants($child, $type));
    }

    return $found;
}

/**
 * Frames until the window has no save panel: the first one opens it and waits, and a timer
 * inside its loop gives $answer the dialog's root once, on the first tick it is open.
 */
function ravenAnswerSave(RavenWindow $window, Closure $answer): void
{
    $answered = false;
    $ticks = 0;
    $timer = Rtimer_create(0.02, R_TRUE, static function () use ($window, $answer, &$answered, &$ticks): void {
        if (! $window->isOpen() || Rwindow_get_popup_count($window->native()) === 0) {
            return;
        }
        $root = Rwindow_get_popup($window->native(), 0);
        if (! $answered) {
            $answered = true;
            $answer($root);
        } elseif (++$ticks > 25) {
            // the answer did not end the panel: end it, so the test fails instead of waiting
            Rdialog_close($root, 0);
        }
    }, null);
    for ($i = 0; $i < 100 && $window->isSaving(); $i++) {
        Rdraw_frame();
        usleep(10_000);
    }
    Rtimer_destroy($timer);
}

/** The root's button whose label reads $text. */
function ravenButtonLabelled(int $root, string $text): int
{
    foreach (ravenDescendants($root, R_WIDGET_BUTTON) as $button) {
        foreach (ravenDescendants($button, R_WIDGET_LABEL) as $label) {
            if (Rlabel_get_text($label) === $text) {
                return $button;
            }
        }
    }

    throw new RuntimeException("No button '{$text}' in the panel.");
}

/** A press and a release in the middle of $widget (a rect is in logical units, the host takes pixels). */
function ravenClick(RavenWindow $window, int $widget): void
{
    Rwidget_get_rect($widget, $x, $y, $width, $height);
    $scale = Rwindow_get_scale($window->native());
    $atX = (int) round(($x + $width / 2) * $scale);
    $atY = (int) round(($y + $height / 2) * $scale);
    Rhost_inject_mouse_move($window->native(), $atX, $atY, 0);
    foreach ([R_TRUE, R_FALSE] as $pressed) {
        Rhost_inject_mouse_button($window->native(), $atX, $atY, R_MOUSE_LEFT, $pressed, 0);
    }
}

it('shows Raven\'s save panel from the next frame with the title, folder and name, and returns at once', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $directory = realpath(sys_get_temp_dir());

    $id = $window->saveFile(new SaveRequest('report.txt', $directory, 'Save the report'));
    expect($id)->toMatch('/^[0-9a-f]{16}$/')
        ->and($window->isSaving())->toBeTrue()
        ->and(Rwindow_get_popup_count($window->native()))->toBe(0);

    $seen = [];
    ravenAnswerSave($window, function (int $root) use (&$seen): void {
        [$title] = ravenDescendants($root, R_WIDGET_LABEL);
        [$browser] = ravenDescendants($root, R_WIDGET_FILE_BROWSER);
        $fields = array_map(fn (int $field): string => Rtext_input_get_text($field), ravenDescendants($browser, R_WIDGET_TEXT_INPUT));
        $seen = [Rlabel_get_text($title), realpath(Rfile_browser_get_directory($browser)), in_array('report.txt', $fields, true)];
        Rdialog_close($root, 0);
    });

    expect($seen)->toBe(['Save the report', $directory, true]);
    ravenSaveAnswers($menus);
});

it('posts null under the panel\'s id when it is cancelled', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $id = $window->saveFile(new SaveRequest('a.txt'));

    ravenAnswerSave($window, function (int $root) use ($window): void {
        Rhost_inject_key($window->native(), R_KEY_ESCAPE, R_TRUE, 0, R_FALSE);
        Rhost_inject_key($window->native(), R_KEY_ESCAPE, R_FALSE, 0, R_FALSE);
    });
    $answers = ravenSaveAnswers($menus);

    expect($answers)->toHaveCount(1)
        ->and($answers[0]->id)->toBe($id)
        ->and($answers[0]->window)->toBe('main')
        ->and($answers[0]->path)->toBeNull()
        ->and($window->isSaving())->toBeFalse();
});

it('posts the chosen path when the panel is accepted', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $directory = realpath(sys_get_temp_dir());
    $name = 'venusian-raven-save-'.bin2hex(random_bytes(4)).'.txt';
    $id = $window->saveFile(new SaveRequest($name, $directory));

    ravenAnswerSave($window, fn (int $root) => ravenClick($window, ravenButtonLabelled($root, 'Save')));
    $answers = ravenSaveAnswers($menus);

    expect($answers)->toHaveCount(1)
        ->and($answers[0]->id)->toBe($id)
        ->and($answers[0]->path)->toBe($directory.'/'.$name)
        ->and($window->isSaving())->toBeFalse();
});

it('answers null and never opens the panel when the window closes before it is shown', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $id = $window->saveFile(new SaveRequest('a.txt'));

    $window->close();
    Rdraw_frame();
    $answers = ravenSaveAnswers($menus);

    expect($answers)->toHaveCount(1)
        ->and($answers[0]->id)->toBe($id)
        ->and($answers[0]->path)->toBeNull();
});

it('answers null when the window closes while the panel is open, and the panel\'s own end posts nothing', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $id = $window->saveFile(new SaveRequest('a.txt'));

    ravenAnswerSave($window, fn (int $root) => $window->close());
    Rdraw_frame();
    $answers = ravenSaveAnswers($menus);

    expect($answers)->toHaveCount(1)
        ->and($answers[0]->id)->toBe($id)
        ->and($answers[0]->path)->toBeNull()
        ->and($window->isOpen())->toBeFalse();
});
