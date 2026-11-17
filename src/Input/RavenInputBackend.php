<?php

namespace Jovian\Toolkits\Raven\Input;

use Closure;
use Jovian\Toolkits\Raven\Primitives\RavenCanvas;
use Jovian\Toolkits\Raven\RavenBridge;
use REvent;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputContext;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\HumanInput\InputBackend;
use Surface\HumanInput\InputChannel;
use Surface\Toolkits\Toolkits;
use Throwable;
use WeakReference;

/**
 * The 'raven' input backend: the keyboard and pointer of Raven canvases, for applications whose
 * toolkit is Raven, on whatever system Raven runs.
 *
 * Every Raven window hands each input event (mouse, wheel, keys, text) to the bridge before any
 * widget sees it, with the widget under the pointer as its target; the backend reads them there
 * and passes them on unchanged.
 *
 * Keys and text go to the canvas that has Raven's keyboard focus: a canvas whose input is open is
 * focusable, so a click or focus() gives it the keys. Raven targets a key at the widget under the
 * pointer, so the focused widget is asked instead.
 *
 * The pointer goes to the canvas under it. A button pressed on a canvas is captured by it: its
 * drags and its release go to that canvas wherever the pointer is. A canvas the pointer leaves is
 * told so. Positions are the canvas's pixels from its top left.
 *
 * Scrolling follows the contract: dy > 0 shows content further down. Raven reports lines, positive
 * up and left; a precise device (wheel_precise) moves 48 logical units a line, so it scrolls in
 * pixels, a notched wheel in notches. Raven reports no gesture phases and not whether scrolling is
 * natural.
 *
 * What a window dispatches is queued as it happens and applied when the backend polls. Raven sends
 * no event when its window loses the keyboard or the pointer leaves it, yet keeps its own state:
 * each poll lets go of the keys and buttons Raven no longer holds, and of the keys of a canvas that
 * lost the focus, and tells the canvas the pointer left the window that it is outside.
 */
final class RavenInputBackend extends InputBackend
{
    /** Logical units a precise wheel moves a line (raven_window.h, REvent::wheel_precise). */
    private const float LINE = 48.0;

    private bool $connected = false;

    private ?RavenBridge $bridge;

    /** Something routing an event failed with: thrown from the next poll, out of Raven. */
    private ?Throwable $failure = null;

    /** @var list<Closure(): void> what to do to channels at the next poll */
    private array $queue = [];

    /** @var array<int, InputChannel> the channel each held button is captured by, by RMouseButton */
    private array $captured = [];

    /** The channel the pointer was last over. */
    private ?InputChannel $hover = null;

    /** @var array<int, array<int, Key>> keys each channel holds, by channel id then RKey */
    private array $held = [];

    /**
     * @param RavenBridge|null $bridge the Raven bridge whose windows it reads; the application's by default
     *
     * @throws HumanInputException When Raven is not here.
     */
    public function __construct(?RavenBridge $bridge = null)
    {
        if (! extension_loaded('raven')) {
            throw new HumanInputException('ext-raven is not loaded.');
        }
        $this->bridge = $bridge;
    }

    public function name(): string
    {
        return 'raven';
    }

    /** Raven's own windows, on any system it runs on, whatever engine draws their canvases. */
    public function serves(InputContext $context): bool
    {
        return $context->toolkit === 'raven';
    }

    public function connect(): static
    {
        if ($this->connected) {
            return $this;
        }

        $bridge = $this->bridge ??= Toolkits::bridge('raven');
        $self = WeakReference::create($this);
        $bridge->watchInput(static function (REvent $event) use ($self): void {
            $self->get()?->seen($event);
        });
        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        if (! $this->connected) {
            return;
        }

        $this->bridge?->watchInput(null);
        $this->closeChannels();
        [$this->queue, $this->captured, $this->hover, $this->held, $this->failure] = [[], [], null, [], null];
        $this->connected = false;
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    /** @throws HumanInputException When $stage is not a Raven canvas. */
    protected function makeChannel(ActorStage $stage): InputChannel
    {
        if (! $stage instanceof RavenCanvas) {
            throw new HumanInputException('The raven input backend reaches Raven canvases only, not a '.$stage::class.'.');
        }

        return parent::makeChannel($stage);
    }

    /**
     * Applies what the windows dispatched since the last poll, then lets go of what Raven no longer
     * holds and of the keys of a canvas that lost the focus.
     *
     * @throws Throwable What routing an event failed with.
     */
    public function poll(): void
    {
        if (! is_null($failure = $this->failure)) {
            $this->failure = null;
            throw $failure;
        }
        if (! $this->connected) {
            return;
        }

        [$queue, $this->queue] = [$this->queue, []];
        foreach ($queue as $apply) {
            $apply();
        }

        $channels = [];
        foreach ($this->channels() as $channel) {
            $channels[spl_object_id($channel)] = $channel;
        }
        $this->reconcile($channels);
        $this->held = array_intersect_key($this->held, $channels);
    }

    /** The Surface key of a Raven key (RKey); UNKNOWN for one Surface does not name. */
    public static function key(int $key): Key
    {
        return match (true) {
            $key >= R_KEY_A && $key <= R_KEY_Z => constant(Key::class.'::'.chr($key)),
            $key >= R_KEY_0 && $key <= R_KEY_9 => constant(Key::class.'::DIGIT_'.chr($key)),
            $key >= R_KEY_F1 && $key <= R_KEY_F12 => constant(Key::class.'::F'.($key - R_KEY_F1 + 1)),
            $key >= R_KEY_KP_0 && $key <= R_KEY_KP_9 => constant(Key::class.'::NUMPAD_'.($key - R_KEY_KP_0)),
            default => match ($key) {
                R_KEY_SPACE => Key::SPACE, R_KEY_APOSTROPHE => Key::APOSTROPHE, R_KEY_COMMA => Key::COMMA,
                R_KEY_MINUS => Key::MINUS, R_KEY_PERIOD => Key::PERIOD, R_KEY_SLASH => Key::SLASH,
                R_KEY_SEMICOLON => Key::SEMICOLON, R_KEY_EQUAL => Key::EQUALS, R_KEY_LEFT_BRACKET => Key::LEFT_BRACKET,
                R_KEY_BACKSLASH => Key::BACKSLASH, R_KEY_RIGHT_BRACKET => Key::RIGHT_BRACKET, R_KEY_GRAVE => Key::GRAVE,
                R_KEY_ESCAPE => Key::ESCAPE, R_KEY_ENTER => Key::ENTER, R_KEY_TAB => Key::TAB, R_KEY_BACKSPACE => Key::BACKSPACE,
                R_KEY_INSERT => Key::INSERT, R_KEY_DELETE => Key::DELETE,
                R_KEY_RIGHT => Key::RIGHT, R_KEY_LEFT => Key::LEFT, R_KEY_DOWN => Key::DOWN, R_KEY_UP => Key::UP,
                R_KEY_PAGE_UP => Key::PAGE_UP, R_KEY_PAGE_DOWN => Key::PAGE_DOWN, R_KEY_HOME => Key::HOME, R_KEY_END => Key::END,
                R_KEY_CAPS_LOCK => Key::CAPS_LOCK, R_KEY_PRINT_SCREEN => Key::PRINT_SCREEN, R_KEY_PAUSE => Key::PAUSE,
                R_KEY_KP_DECIMAL => Key::NUMPAD_PERIOD, R_KEY_KP_DIVIDE => Key::NUMPAD_DIVIDE, R_KEY_KP_MULTIPLY => Key::NUMPAD_MULTIPLY,
                R_KEY_KP_SUBTRACT => Key::NUMPAD_MINUS, R_KEY_KP_ADD => Key::NUMPAD_PLUS, R_KEY_KP_ENTER => Key::NUMPAD_ENTER,
                R_KEY_LEFT_SHIFT => Key::LEFT_SHIFT, R_KEY_LEFT_CTRL => Key::LEFT_CTRL, R_KEY_LEFT_ALT => Key::LEFT_ALT, R_KEY_LEFT_SUPER => Key::LEFT_META,
                R_KEY_RIGHT_SHIFT => Key::RIGHT_SHIFT, R_KEY_RIGHT_CTRL => Key::RIGHT_CTRL, R_KEY_RIGHT_ALT => Key::RIGHT_ALT, R_KEY_RIGHT_SUPER => Key::RIGHT_META,
                R_KEY_MENU => Key::MENU,
                default => Key::UNKNOWN,
            },
        };
    }

    /** The Surface modifiers of a Raven RModifier mask. */
    public static function modifiers(int $mask): Modifiers
    {
        return new Modifiers(
            shift: ($mask & R_MOD_SHIFT) !== 0,
            ctrl: ($mask & R_MOD_CTRL) !== 0,
            alt: ($mask & R_MOD_ALT) !== 0,
            meta: ($mask & R_MOD_SUPER) !== 0,
        );
    }

    /**
     * An event a Raven window is dispatching. Routed now, while the layout and the focus are the
     * ones the user saw; applied at the next poll.
     *
     * @internal
     */
    public function seen(REvent $event): void
    {
        try {
            $this->routeEvent($event);
        } catch (Throwable $e) {
            $this->failure ??= $e;
        }
    }

    private function routeEvent(REvent $event): void
    {
        switch ($event->type) {
            case R_EVENT_MOUSE_MOVE:
                $dragging = [];
                foreach ($this->captured as $to) {
                    if (! in_array($to, $dragging, true)) {
                        $dragging[] = $to;
                        $this->queuePointer($to, $event);
                    }
                }
                $this->hovers($this->under($event), $event, $dragging);

                return;

            case R_EVENT_MOUSE_DOWN:
                $under = $this->under($event);
                $this->hovers($under, $event);
                $button = self::button($event->button);
                if (is_null($button) || is_null($under)) {
                    return;
                }
                $this->captured[$event->button] = $under;
                $this->queueButton($under, $event, $button, true);

                return;

            case R_EVENT_MOUSE_UP:
                $button = self::button($event->button);
                $to = $this->captured[$event->button] ?? null;
                unset($this->captured[$event->button]);
                if (! is_null($button) && ! is_null($to)) {
                    $this->queueButton($to, $event, $button, false);
                }
                $this->hovers($this->under($event), $event, is_null($to) ? [] : [$to]);

                return;

            case R_EVENT_MOUSE_WHEEL:
                $under = $this->under($event);
                $this->hovers($under, $event);
                if (! is_null($under)) {
                    $precise = $event->wheel_precise === R_TRUE;
                    $per_line = $precise ? self::LINE * Rwindow_get_scale($event->window) : 1.0;
                    [$dx, $dy] = [-$event->wheel_x * $per_line, -$event->wheel_y * $per_line];
                    $this->queue[] = static fn () => $under->scroll($dx, $dy, $precise);
                }

                return;

            case R_EVENT_KEY_DOWN:
            case R_EVENT_KEY_UP:
                $to = $this->focused($event->window);
                if (is_null($to)) {
                    return;
                }
                $id = spl_object_id($to);
                $down = $event->type === R_EVENT_KEY_DOWN;
                // a repeat or a stray key-up changes nothing: the channel sends mail only for a change
                $key = self::key($event->key);
                if ($down) {
                    $this->held[$id][$event->key] = $key;
                } else {
                    unset($this->held[$id][$event->key]);
                }
                $modifiers = self::modifiers($event->modifiers);
                $this->queue[] = static function () use ($to, $modifiers, $key, $down): void {
                    $to->modifiers($modifiers);
                    $to->key($key, $down);
                };

                return;

            case R_EVENT_TEXT:
                $to = $this->focused($event->window);
                if (! is_null($to) && $event->codepoint > 0) {
                    $text = mb_chr($event->codepoint, 'UTF-8');
                    $this->queue[] = static fn () => $to->text($text);
                }

                return;
        }
    }

    /** The open channel of the canvas that is $widget in $window; null for none. */
    private function canvasChannel(int $widget, int $window): ?InputChannel
    {
        foreach ($this->channels() as $channel) {
            $canvas = $channel->stage();
            if ($canvas instanceof RavenCanvas && $canvas->control() === $widget && $canvas->windowRid() === $window) {
                return $channel;
            }
        }

        return null;
    }

    /** The open channel of the canvas under the pointer; null for none. */
    private function under(REvent $event): ?InputChannel
    {
        return $this->canvasChannel($event->target, $event->window);
    }

    /** The open channel of the canvas with the keyboard focus in $window; null for none. */
    private function focused(int $window): ?InputChannel
    {
        return $this->canvasChannel(Rwindow_get_focused_widget($window), $window);
    }

    /**
     * The pointer is over $under now (null: no canvas with open input): the canvas it was over and
     * left is told where it is, outside; the one it is over gets where it is. $except already got a
     * position from this event.
     *
     * @param list<InputChannel> $except
     */
    private function hovers(?InputChannel $under, REvent $event, array $except = []): void
    {
        if (! is_null($this->hover) && $this->hover !== $under && ! in_array($this->hover, $except, true) && ! $this->hover->closed()) {
            $this->queuePointer($this->hover, $event);
        }
        $this->hover = $under;
        if (! is_null($under) && ! in_array($under, $except, true)) {
            $this->queuePointer($under, $event);
        }
    }

    private function queuePointer(InputChannel $to, REvent $event): void
    {
        [$x, $y, $inside] = $this->position($to, $event->x, $event->y, $event->window);
        $this->queue[] = static fn () => $to->pointer($x, $y, $inside);
    }

    private function queueButton(InputChannel $to, REvent $event, MouseButton $button, bool $down): void
    {
        [$x, $y, $inside] = $this->position($to, $event->x, $event->y, $event->window);
        $modifiers = self::modifiers($event->modifiers);
        $this->queue[] = static function () use ($to, $x, $y, $inside, $modifiers, $button, $down): void {
            $to->modifiers($modifiers);
            $to->pointer($x, $y, $inside);
            $to->button($button, $down);
        };
    }

    /**
     * A point of $window, in its logical units, as $to's canvas's pixels from its top left, and
     * whether it is inside the canvas.
     *
     * @return array{float, float, bool}
     */
    private function position(InputChannel $to, float $x, float $y, int $window): array
    {
        /** @var RavenCanvas $canvas */
        $canvas = $to->stage();
        Rwidget_get_rect($canvas->control(), $left, $top, $width, $height);
        [$x, $y] = [$x - $left, $y - $top];
        $scale = Rwindow_get_scale($window);

        return [$x * $scale, $y * $scale, $x >= 0.0 && $x < $width && $y >= 0.0 && $y < $height];
    }

    /**
     * Lets go of the keys and buttons Raven no longer holds (its window lost the keyboard, a button
     * came up while Raven held no event for it) and of the keys of a canvas that lost the focus;
     * tells the canvas the pointer was over that it left a window the pointer is no longer in.
     *
     * @param array<int, InputChannel> $channels by id
     */
    private function reconcile(array $channels): void
    {
        foreach ($channels as $id => $channel) {
            if (($this->held[$id] ?? []) === []) {
                continue;
            }
            /** @var RavenCanvas $canvas */
            $canvas = $channel->stage();
            $window = $canvas->windowRid();
            $focused = Rwindow_get_focused_widget($window) === $canvas->control();
            foreach ($this->held[$id] as $code => $key) {
                if (! $focused || Rwindow_is_key_down($window, $code) !== R_TRUE) {
                    $channel->key($key, false);
                    unset($this->held[$id][$code]);
                }
            }
        }

        foreach ($this->captured as $code => $channel) {
            if ($channel->closed()) {
                unset($this->captured[$code]);

                continue;
            }
            /** @var RavenCanvas $canvas */
            $canvas = $channel->stage();
            if (Rwindow_is_mouse_button_down($canvas->windowRid(), $code) !== R_TRUE) {
                unset($this->captured[$code]);
                if (! is_null($button = self::button($code))) {
                    $channel->button($button, false);
                }
            }
        }

        if (! is_null($hover = $this->hover)) {
            /** @var RavenCanvas $canvas */
            $canvas = $hover->stage();
            if ($hover->closed()) {
                $this->hover = null;
            } elseif (Rwindow_is_mouse_inside($window = $canvas->windowRid()) !== R_TRUE) {
                Rwindow_get_mouse_position($window, $x, $y);
                [$x, $y] = $this->position($hover, (float) $x, (float) $y, $window);
                $hover->pointer($x, $y, false);
                $this->hover = null;
            }
        }
    }

    private static function button(int $button): ?MouseButton
    {
        return match ($button) {
            R_MOUSE_LEFT => MouseButton::LEFT,
            R_MOUSE_RIGHT => MouseButton::RIGHT,
            R_MOUSE_MIDDLE => MouseButton::MIDDLE,
            R_MOUSE_X1 => MouseButton::X1,
            R_MOUSE_X2 => MouseButton::X2,
            default => null,
        };
    }
}
