<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use Jovian\Toolkits\Raven\Input\RavenInputBackend;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputContext;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Mail\KeyChanged;
use Surface\Contracts\HumanInput\Mail\PointerButtonChanged;
use Surface\Contracts\HumanInput\Mail\PointerMoved;
use Surface\Contracts\HumanInput\Mail\PointerScrolled;
use Surface\Contracts\HumanInput\Mail\TextTyped;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\HumanInput\ScrollPhase;

afterEach(fn () => riCloseWindows());

it('serves Raven windows on any system, and nothing else', function (): void {
    riApp();
    $backend = new RavenInputBackend();

    expect($backend->serves(new InputContext('raven', 'velvet', 'mac')))->toBeTrue()
        ->and($backend->serves(new InputContext('raven', 'thorvg', 'linux')))->toBeTrue()
        ->and($backend->serves(new InputContext('appkit', 'velvet', 'mac')))->toBeFalse()
        ->and($backend->serves(new InputContext('gtk', 'velvet', 'linux')))->toBeFalse();
});

it('lays the two canvases out as the tests expect', function (): void {
    riInput();
    [$window, $top, $bottom] = riWindow();

    expect(riRect($top))->toBe([0.0, 0.0, 300.0, 100.0])
        ->and(riRect($bottom))->toBe([0.0, 100.0, 300.0, 100.0])
        ->and(Rwindow_get_scale($window->rid()))->toBe(2.0);
});

it('reads the pointer in the canvas\'s pixels from its top left', function (): void {
    $input = riInput();
    [$window, $top, $bottom] = riWindow();
    [$upper, $lower] = [$top->input(), $bottom->input()];

    // injected positions are the surface's pixels: (80, 260) is (40, 130) logical, 30 into the bottom canvas
    riDeliver($input, fn () => Rhost_inject_mouse_move($window->rid(), 80, 260, 0));

    expect([$lower->mouse()->x(), $lower->mouse()->y(), $lower->mouse()->inside()])->toBe([80.0, 60.0, true])
        ->and($upper->mouse()->inside())->toBeFalse();
});

it('tells the canvas the pointer left that it is outside, and the one it came to where it is', function (): void {
    $input = riInput();
    [$window, $top, $bottom] = riWindow();
    [$upper, $lower] = [$top->input(), $bottom->input()];

    riDeliver($input, fn () => Rhost_inject_mouse_move($window->rid(), 80, 60, 0));
    riMailSent();
    riDeliver($input, fn () => Rhost_inject_mouse_move($window->rid(), 80, 260, 0));

    $moved = array_values(array_filter(riMailSent(), fn (object $m): bool => $m instanceof PointerMoved));
    expect(array_map(fn (PointerMoved $m): array => [$m->stage === $top ? 'top' : 'bottom', $m->inside], $moved))->toBe([['top', false], ['bottom', true]])
        ->and([$upper->mouse()->x(), $upper->mouse()->y()])->toBe([80.0, 260.0]);
});

it('tells the canvas under the pointer it is outside once the pointer leaves the window', function (): void {
    $input = riInput();
    [$window, $top] = riWindow();
    $channel = $top->input();

    riDeliver($input, fn () => Rhost_inject_mouse_move($window->rid(), 80, 60, 0));
    riDeliver($input, fn () => Rhost_inject_mouse_leave($window->rid()));

    expect($channel->mouse()->inside())->toBeFalse();
});

it('gives the keys to the canvas a click focused, not to the one under the pointer', function (): void {
    $input = riInput();
    [$window, $top, $bottom] = riWindow();
    [$upper, $lower] = [$top->input(), $bottom->input()];

    riDeliver($input, function () use ($window): void {
        Rhost_inject_mouse_button($window->rid(), 80, 60, R_MOUSE_LEFT, R_TRUE, 0);
        Rhost_inject_mouse_button($window->rid(), 80, 60, R_MOUSE_LEFT, R_FALSE, 0);
        Rhost_inject_mouse_move($window->rid(), 80, 260, 0);         // over the bottom canvas now
        Rhost_inject_key($window->rid(), R_KEY_A, R_TRUE, R_MOD_SHIFT, R_FALSE);
        Rhost_inject_text($window->rid(), ord('A'));
    });

    expect($top->hasFocus())->toBeTrue()
        ->and($upper->keyboard()->isDown(Key::A))->toBeTrue()
        ->and($upper->keyboard()->text())->toBe('A')
        ->and($upper->keyboard()->modifiers()->shift)->toBeTrue()
        ->and($lower->keyboard()->isDown(Key::A))->toBeFalse();

    riDeliver($input, fn () => Rhost_inject_key($window->rid(), R_KEY_A, R_FALSE, 0, R_FALSE));

    expect($upper->keyboard()->isDown(Key::A))->toBeFalse();
});

it('reads a held key once, however often it repeats', function (): void {
    $input = riInput();
    [$window, $top] = riWindow();
    $channel = $top->input();
    $top->focus();
    riMailSent();

    riDeliver($input, function () use ($window): void {
        Rhost_inject_key($window->rid(), R_KEY_LEFT, R_TRUE, 0, R_FALSE);
        Rhost_inject_key($window->rid(), R_KEY_LEFT, R_TRUE, 0, R_TRUE);
        Rhost_inject_key($window->rid(), R_KEY_LEFT, R_TRUE, 0, R_TRUE);
    });

    $keys = array_values(array_filter(riMailSent(), fn (object $m): bool => $m instanceof KeyChanged));
    expect(count($keys))->toBe(1)
        ->and($keys[0]->key)->toBe(Key::LEFT)
        ->and($channel->keyboard()->isDown(Key::LEFT))->toBeTrue();
});

it('takes no keys into a canvas whose input is not open', function (): void {
    $input = riInput();
    [$window, $top, $bottom] = riWindow();
    $channel = $bottom->input();

    riDeliver($input, function () use ($window): void {
        Rhost_inject_mouse_button($window->rid(), 80, 60, R_MOUSE_LEFT, R_TRUE, 0);   // the top canvas: no input
        Rhost_inject_mouse_button($window->rid(), 80, 60, R_MOUSE_LEFT, R_FALSE, 0);
        Rhost_inject_key($window->rid(), R_KEY_B, R_TRUE, 0, R_FALSE);
    });

    expect($top->hasFocus())->toBeFalse()
        ->and($channel->keyboard()->isDown(Key::B))->toBeFalse();
});

it('lets go of held keys when the window loses the keyboard', function (): void {
    $input = riInput();
    [$window, $top] = riWindow();
    $channel = $top->input();
    $top->focus();

    riDeliver($input, fn () => Rhost_inject_key($window->rid(), R_KEY_W, R_TRUE, 0, R_FALSE));
    expect($channel->keyboard()->isDown(Key::W))->toBeTrue();

    // Raven sends no key-up: it forgets the key
    riDeliver($input, fn () => Rhost_inject_focus($window->rid(), R_FALSE));

    expect($channel->keyboard()->isDown(Key::W))->toBeFalse();
});

it('lets go of a canvas\'s keys when another widget takes the focus', function (): void {
    $input = riInput();
    [$window, $top, $bottom] = riWindow();
    [$upper] = [$top->input(), $bottom->input()];
    $top->focus();

    riDeliver($input, fn () => Rhost_inject_key($window->rid(), R_KEY_W, R_TRUE, 0, R_FALSE));
    $bottom->focus();
    $input->poll();

    expect($upper->keyboard()->isDown(Key::W))->toBeFalse();
});

it('keeps a pressed button with its canvas, through its drag and its release', function (): void {
    $input = riInput();
    [$window, $top, $bottom] = riWindow();
    [$upper, $lower] = [$top->input(), $bottom->input()];

    riDeliver($input, function () use ($window): void {
        Rhost_inject_mouse_button($window->rid(), 80, 60, R_MOUSE_LEFT, R_TRUE, 0);
        Rhost_inject_mouse_move($window->rid(), 80, 260, 0);
    });

    expect($upper->mouse()->isDown(MouseButton::LEFT))->toBeTrue()
        ->and([$upper->mouse()->x(), $upper->mouse()->y()])->toBe([80.0, 260.0])
        ->and($lower->mouse()->isDown(MouseButton::LEFT))->toBeFalse();

    riMailSent();
    riDeliver($input, fn () => Rhost_inject_mouse_button($window->rid(), 80, 260, R_MOUSE_LEFT, R_FALSE, 0));

    $buttons = array_values(array_filter(riMailSent(), fn (object $m): bool => $m instanceof PointerButtonChanged));
    expect(count($buttons))->toBe(1)
        ->and($buttons[0]->stage)->toBe($top)
        ->and($buttons[0]->down)->toBeFalse()
        ->and($upper->mouse()->isDown(MouseButton::LEFT))->toBeFalse();
});

it('reads a notched wheel in notches, dy > 0 for content further down', function (): void {
    $input = riInput();
    [$window, $top] = riWindow();
    $channel = $top->input();
    riDeliver($input, fn () => Rhost_inject_mouse_move($window->rid(), 80, 60, 0));

    // Raven's wheel is positive up: -2 is the wheel rolled toward the user, two notches
    riDeliver($input, fn () => Rhost_inject_mouse_wheel($window->rid(), 0.0, -2.0, 0));

    expect($channel->mouse()->wheel())->toBe(['dx' => 0.0, 'dy' => 2.0])
        ->and($channel->mouse()->precise())->toBeFalse()
        ->and($channel->mouse()->phase())->toBe(ScrollPhase::NONE);
});

it('reads a precise wheel in the canvas\'s pixels: 48 logical units a line', function (): void {
    $input = riInput();
    [$window, $top] = riWindow();
    $channel = $top->input();

    // Rhost_inject_mouse_wheel cannot say precise: the event a trackpad brings, handed in as Raven hands it
    $input->backend()->seen(new REvent(
        type: R_EVENT_MOUSE_WHEEL, window: $window->rid(), target: $top->control(), x: 40.0, y: 30.0,
        wheel_x: 0.25, wheel_y: -0.5, wheel_precise: R_TRUE,
    ));
    $input->poll();

    $mail = array_values(array_filter(riMailSent(), fn (object $m): bool => $m instanceof PointerScrolled));
    expect($channel->mouse()->wheel())->toBe(['dx' => -24.0, 'dy' => 48.0])
        ->and($channel->mouse()->precise())->toBeTrue()
        ->and(count($mail))->toBe(1);
});

it('turns Raven keys into Surface keys', function (int $raven, Key $key): void {
    riApp();

    expect(RavenInputBackend::key($raven))->toBe($key);
})->with([
    'a letter' => [R_KEY_Q, Key::Q],
    'a digit' => [R_KEY_7, Key::DIGIT_7],
    'a function key' => [R_KEY_F11, Key::F11],
    'a keypad digit' => [R_KEY_KP_3, Key::NUMPAD_3],
    'an arrow' => [R_KEY_DOWN, Key::DOWN],
    'super' => [R_KEY_RIGHT_SUPER, Key::RIGHT_META],
    'the keypad point' => [R_KEY_KP_DECIMAL, Key::NUMPAD_PERIOD],
    'unknown' => [R_KEY_UNKNOWN, Key::UNKNOWN],
]);

it('refuses a stage that is not a Raven canvas', function (): void {
    $input = riInput();

    expect(fn () => $input->backend()->open(Mockery::mock(\Surface\Contracts\Graphics\ActorStage::class)))
        ->toThrow(HumanInputException::class, 'Raven canvases only');
});

it('stops reading the windows once disconnected', function (): void {
    $input = riInput();
    [$window, $top] = riWindow();
    $channel = $top->input();
    $input->backend()->disconnect();

    Rhost_inject_mouse_move($window->rid(), 80, 60, 0);

    expect($channel->closed())->toBeTrue()
        ->and(riMailSent())->toBe([]);
});
