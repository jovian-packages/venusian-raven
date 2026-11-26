<?php

namespace Jovian\Toolkits\Raven;

use CFFileDescriptor;
use CFRunLoop;
use CFRunLoopSource;
use Closure;
use RInitDesc;
use RuntimeException;
use Surface\Contracts\Toolkits\Styling\ColorScheme;
use Surface\Contracts\Toolkits\Toolkit;
use Jovian\Toolkits\Raven\UI\Support\RavenFrames;

/**
 * Raven's one context per process. Raven has no loop of its own: a pump asks it for frames.
 *
 * Raven watches no descriptor of the application's. On macOS its wait is Cocoa's run loop, so the
 * loop's descriptor joins that run loop as a CFFileDescriptor (ext-appkit) whose callout wakes
 * Raven: a pump is one Rwait_frame() that a loop wake ends at once. Elsewhere (X11, Wayland, the
 * headless null platform) Raven polls only its own descriptors, so a pump waits in Rwait_frame()
 * slices and checks the loop's descriptor between them: a loop wake ends the pump within a slice.
 */
class Raven implements Toolkit
{
    /**
     * The loop's waiter descriptor as a run-loop source, while joined on macOS.
     */
    protected ?CFFileDescriptor $wake = null;

    protected ?CFRunLoopSource $wake_source = null;

    /**
     * The loop's waiter descriptor duplicated as a stream for stream_select(), while joined
     * anywhere Raven's wait is not Cocoa's run loop.
     *
     * @var resource|null
     */
    protected mixed $wake_stream = null;

    /**
     * Called when Raven's own quit fires (the macOS application menu, Cmd+Q, the dock, app.quit).
     *
     * @var (Closure(): void)|null
     */
    protected ?Closure $on_quit = null;

    /** Set by initialize(): the backend Raven started on runs effects. */
    private static bool $draws_effects = false;

    /**
     * @param string $application_name Raven's app_name: the macOS application menu's title.
     * @param string $backend 'vulkan' or 'null'.
     * @param bool $headless Raven's null platform: no OS window opens.
     * @param float $ui_scale A global scale on top of the monitor's; 0 keeps Raven's 1.0.
     * @param string|null $theme A built-in theme's name or a theme XML path; null keeps Raven's.
     * @param float $slice Longest single wait of a sliced pump, in seconds.
     * @throws RuntimeException Raven did not start, or the theme is not one Raven knows.
     */
    public function __construct(
        protected readonly string $application_name,
        protected readonly string $backend,
        protected readonly bool $headless,
        protected readonly float $ui_scale,
        protected readonly ?string $theme,
        protected readonly float $slice,
    ) {
        if ($this->slice <= 0.0) {
            throw new RuntimeException("raven.wait_slice_ms must be above 0, got ".($this->slice * 1000).'.');
        }

        if (Ris_initialized() !== R_TRUE) {
            $this->initialize();
        }

        $this->applyTheme();
        RavenFrames::install();
    }

    /**
     * Whether Raven runs effects (raven_shader.h) here: its Vulkan backend does; the null
     * backend draws an effect as a plain rectangle in its colour.
     */
    public static function drawsEffects(): bool
    {
        return self::$draws_effects;
    }

    /**
     * Hand Raven's quit to the application from connect() on: $quit runs instead of Rquit.
     *
     * @param Closure(): void $quit
     */
    public function onQuit(Closure $quit): static
    {
        $this->on_quit = $quit;

        return $this;
    }

    /**
     * Raven's quit asks the application, which decides: with app.quit bound Raven does not stop
     * itself, and on macOS a Cmd+Q with a window open is cancelled until the app closes it.
     */
    public function connect(): void
    {
        $quit = $this->on_quit;
        Raction_bind('app.quit', static function () use ($quit): void {
            if (! is_null($quit)) {
                $quit();
            }
        }, null);
    }

    /**
     * Give app.quit back to Raven's built-in.
     */
    public function disconnect(): void
    {
        Raction_bind('app.quit', null, null);
    }

    /**
     * Wait at most $budget_ns for Raven's events, tasks, timers and redraws, then produce a
     * frame. A wake of the loop, or anything Raven had to do, ends the wait.
     *
     * @param int $budget_ns Zero produces one frame without waiting.
     * @return int Frames produced.
     */
    public function pump(int $budget_ns): int
    {
        if ($budget_ns <= 0) {
            Rdraw_frame();

            return 1;
        }

        if (! is_null($this->wake)) {
            // The callout is one-shot: re-armed for each wait, so a descriptor the loop has not
            // read yet cannot keep waking Raven while it drains.
            $this->wake->enableCallBacks(kCFFileDescriptorReadCallBack);
            Rwait_frame($budget_ns / 1e9);

            return 1;
        }

        return $this->pumpSliced($budget_ns);
    }

    /**
     * Fold the loop's waiter into Raven's wait: readable ends the pump.
     *
     * @throws RuntimeException The descriptor cannot be watched.
     */
    public function wakeDescriptor(int $fd): void
    {
        $this->releaseWake();

        if ($this->waitsInRunLoop()) {
            $this->wake = CFFileDescriptor::create(null, $fd, false, static function (): void {
                Rwake_up();
            }) ?? throw new RuntimeException("CoreFoundation did not take the loop's descriptor {$fd}.");
            $this->wake_source = $this->wake->createRunLoopSource(null, 0)
                ?? throw new RuntimeException("CoreFoundation made no run-loop source for the loop's descriptor {$fd}.");
            CFRunLoop::getMain()->addSource($this->wake_source, kCFRunLoopCommonModes);

            return;
        }

        $stream = @fopen("php://fd/{$fd}", 'r');
        if ($stream === false) {
            throw new RuntimeException("The loop's descriptor {$fd} cannot be watched.");
        }
        stream_set_blocking($stream, false);
        $this->wake_stream = $stream;
    }

    /**
     * Whether Raven waits in Cocoa's run loop with ext-appkit there to add the loop's descriptor
     * to it. Headless Raven runs its null platform, which waits on a condition variable instead.
     */
    public function waitsInRunLoop(): bool
    {
        return device_os_family() === 'mac' && ! $this->headless && extension_loaded('appkit');
    }

    public function isHeadless(): bool
    {
        return $this->headless;
    }

    /**
     * Rwait_frame() in slices up to the budget. Returns once the loop's descriptor is readable,
     * once Raven returned before its slice ran out (it had an event, a task, a timer or a frame
     * to draw), or once the budget is spent.
     */
    protected function pumpSliced(int $budget_ns): int
    {
        $frames = 0;
        $deadline = hrtime(true) + $budget_ns;
        $slice_ns = (int) ($this->slice * 1e9);

        while (true) {
            $woken = $this->woken();
            $wait_ns = $woken ? 0 : max(0, min($slice_ns, $deadline - hrtime(true)));

            $started = hrtime(true);
            Rwait_frame($wait_ns / 1e9);
            $frames++;
            $now = hrtime(true);

            // (half a millisecond is the slack of a timed wait that ran its course)
            $busy = $wait_ns > 0 && $now - $started < $wait_ns - 500_000;
            if ($woken || $busy || $now >= $deadline || $this->woken()) {
                return $frames;
            }
        }
    }

    /**
     * True when the loop's descriptor is readable: the loop has work, the pump returns.
     */
    protected function woken(): bool
    {
        if (! is_resource($this->wake_stream)) {
            return false;
        }
        $read = [$this->wake_stream];
        $none = null;

        return @stream_select($read, $none, $none, 0) > 0;
    }

    protected function releaseWake(): void
    {
        if (! is_null($this->wake_source)) {
            CFRunLoop::getMain()->removeSource($this->wake_source, kCFRunLoopCommonModes);
            $this->wake_source = null;
        }
        $this->wake?->invalidate();
        $this->wake = null;

        if (is_resource($this->wake_stream)) {
            fclose($this->wake_stream);
        }
        $this->wake_stream = null;
    }

    /**
     * Rinit_ex once per process. A failure names Raven's own reason (no Vulkan loader, no display).
     *
     * @throws RuntimeException
     */
    protected function initialize(): void
    {
        $backend = match ($this->backend) {
            'vulkan' => R_BACKEND_VULKAN,
            'null' => R_BACKEND_NULL,
            default => throw new RuntimeException("raven.backend is 'vulkan' or 'null', got '{$this->backend}'."),
        };

        $desc = new RInitDesc(
            backend: $this->headless ? R_BACKEND_NULL : $backend,
            app_name: $this->application_name,
            headless: $this->headless ? R_TRUE : R_FALSE,
            ui_scale: $this->ui_scale,
        );

        if (Rinit_ex($desc) !== R_TRUE) {
            Rerror_get_last($detail, $severity);
            throw new RuntimeException('Raven did not start: '.($detail ?: 'Rinit_ex failed').'.');
        }

        self::$draws_effects = ! $this->headless && $backend === R_BACKEND_VULKAN;
    }

    /**
     * Draw in the dark or the light member of the theme in use: a theme's family is its name
     * with the trailing Dark or Light swapped (GlassDark and GlassLight, Dark and Light,
     * HighContrastDark and HighContrastLight). Run after applyTheme(), so it wins. The system
     * scheme changes nothing; a theme already of the scheme asked is kept.
     *
     * @throws RuntimeException The theme has no member of the other scheme.
     */
    public function applyColorScheme(ColorScheme $scheme): void
    {
        if ($scheme === ColorScheme::SYSTEM || (Rtheme_is_dark() === R_TRUE) === ($scheme === ColorScheme::DARK)) {
            return;
        }

        $name = Rtheme_get_name();
        $other = $scheme === ColorScheme::DARK ? ['Light', 'Dark'] : ['Dark', 'Light'];
        $member = str_ends_with($name, $other[0]) ? substr($name, 0, -strlen($other[0])).$other[1] : null;

        if (is_null($member) || Rtheme_use($member) !== R_TRUE) {
            throw new RuntimeException("toolkits.color_scheme '{$scheme->value}': the Raven theme '{$name}' has no ".strtolower($other[1]).' member of its family.');
        }
    }

    /**
     * raven.theme: a built-in name, or a theme XML path whose own name is then used.
     *
     * @throws RuntimeException
     */
    protected function applyTheme(): void
    {
        if (is_null($this->theme) || $this->theme === '') {
            return;
        }

        $name = $this->theme;
        if (is_file($name)) {
            $name = Rtheme_load($name)
                ?? throw new RuntimeException("raven.theme: Raven could not load '{$this->theme}'.");
        }

        if (Rtheme_use($name) !== R_TRUE) {
            $known = [];
            for ($i = 0, $n = Rtheme_get_count(); $i < $n; $i++) {
                $known[] = Rtheme_get_name_at($i);
            }
            throw new RuntimeException("raven.theme '{$name}' is not a Raven theme; known: ".implode(', ', $known).'.');
        }
    }
}
