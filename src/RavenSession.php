<?php

namespace Jovian\Toolkits\Raven;

use RInitDesc;
use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Toolkits\ToolkitSession;
use WeakMap;

/**
 * Raven's one context per process. Raven has no descriptor watch, so a pump waits in
 * Rwait_frame() slices and checks the loop's descriptor between them: a loop wake ends the
 * pump within one slice.
 */
class RavenSession extends ToolkitSession
{
    /** Longest single Rwait_frame() inside a pump, in seconds: the latency of a loop wake. */
    public const float SLICE = 0.008;

    /** The loop's waiter descriptor, duplicated as a stream for stream_select(), while joined. */
    protected mixed $wake = null;

    /**
     * Primitives whose size is watched: compared after every pump.
     * @var WeakMap<object, array{int, int}>|null
     */
    protected ?WeakMap $watched = null;

    /** @var array<string, \Closure(): void> run after every pump, by key: windows checking their focus */
    protected array $after_pump = [];

    /** The About dialog's content while it is open. */
    protected int $about = 0;

    /** Raven's own quit (its macOS application menu, app.quit) has been posted. */
    protected bool $quit_posted = false;

    public function __construct(
        protected readonly string $application_name,
        protected readonly string $backend,
        protected readonly bool $headless,
        protected readonly float $ui_scale,
        protected readonly ?string $theme = null,
    ) {
        parent::__construct();
    }

    /**
     * Rinit_ex once per process. A failure names Raven's own reason (no Vulkan loader, no display).
     * @throws ToolkitRuntimeException
     */
    protected function initializeEngine(): void
    {
        if (Ris_initialized()) {
            return;
        }
        $backend = match ($this->backend) {
            'vulkan' => R_BACKEND_VULKAN,
            'null' => R_BACKEND_NULL,
            default => throw new ToolkitRuntimeException("raven.backend is 'vulkan' or 'null', got '{$this->backend}'."),
        };
        $desc = new RInitDesc(
            backend: $this->headless ? R_BACKEND_NULL : $backend,
            app_name: $this->application_name,
            headless: $this->headless ? R_TRUE : R_FALSE,
            ui_scale: $this->ui_scale,
        );
        if (Rinit_ex($desc) !== R_TRUE) {
            Rerror_get_last($detail, $severity);
            throw new ToolkitRuntimeException('Raven did not start: '.($detail ?: 'Rinit_ex failed').'.');
        }
        $this->applyTheme();
    }

    /**
     * raven.theme: a built-in name, or a theme XML path whose own name is then used.
     * @throws ToolkitRuntimeException
     */
    protected function applyTheme(): void
    {
        if ($this->theme === null) {
            return;
        }
        $name = $this->theme;
        if (is_file($name)) {
            $name = Rtheme_load($name)
                ?? throw new ToolkitRuntimeException("raven.theme: Raven could not load '{$this->theme}'.");
        }
        if (Rtheme_use($name) !== R_TRUE) {
            $known = [];
            for ($i = 0, $n = Rtheme_get_count(); $i < $n; $i++) {
                $known[] = Rtheme_get_name_at($i);
            }
            throw new ToolkitRuntimeException("raven.theme '{$name}' is not a Raven theme; known: ".implode(', ', $known).'.');
        }
    }

    protected function connectToEngine(): void {}

    protected function disconnectEngine(): void {}

    /**
     * Wait at most $budget_ns for Raven's events, timers and redraws, in slices; a readable loop
     * descriptor ends the wait at the next slice. Zero produces one frame without waiting.
     *
     * @return int Frames produced.
     */
    public function pump(int $budget_ns): int
    {
        $frames = 0;
        if ($budget_ns <= 0) {
            Rdraw_frame();
            $frames++;
        } else {
            $deadline = hrtime(true) + $budget_ns;
            do {
                $left = ($deadline - hrtime(true)) / 1e9;
                Rwait_frame(max(0.0, min(self::SLICE, $left)));
                $frames++;
            } while (hrtime(true) < $deadline && ! $this->woken());
        }

        // Raven's application menu (macOS) and its app.quit action stop Raven's loop: the app decides.
        if (Ris_running() !== R_TRUE && ! $this->quit_posted) {
            $this->quit_posted = true;
            $this->post(new QuitRequested());
        }

        $this->checkWatched();
        foreach ($this->after_pump as $check) {
            $check();
        }

        return $frames;
    }

    /** True when the loop's descriptor is readable: the loop has work, the pump returns. */
    protected function woken(): bool
    {
        if (! is_resource($this->wake)) {
            return false;
        }
        $read = [$this->wake];
        $none = null;

        return @stream_select($read, $none, $none, 0) > 0;
    }

    protected function wakeDescriptor(int $fd): void
    {
        $this->releaseWakeDescriptor();
        $stream = @fopen("php://fd/{$fd}", 'r');
        if ($stream === false) {
            throw new ToolkitRuntimeException("The loop's descriptor {$fd} cannot be watched.");
        }
        stream_set_blocking($stream, false);
        $this->wake = $stream;
    }

    protected function releaseWakeDescriptor(): void
    {
        if (is_resource($this->wake)) {
            fclose($this->wake);
        }
        $this->wake = null;
    }

    /**
     * Watch $primitive's size: $read answers [width, height]; a change posts through $changed.
     * @param \Closure(): array{int, int} $read
     * @param \Closure(int, int): void $changed
     */
    public function watch(object $primitive, \Closure $read, \Closure $changed): void
    {
        $this->watched ??= new WeakMap();
        // nothing seen yet: the first check reports the size as it is, as the other toolkits do, so
        // a primitive that is already laid out when it starts watching still hears its size
        $this->watched[$primitive] = [$read, $changed, null];
    }

    public function unwatch(object $primitive): void
    {
        if (! is_null($this->watched)) {
            unset($this->watched[$primitive]);
        }
    }

    /** Run $check after every pump under $key (null removes it): Raven has no notification for it. */
    public function afterPump(string $key, ?\Closure $check): void
    {
        if (is_null($check)) {
            unset($this->after_pump[$key]);

            return;
        }
        $this->after_pump[$key] = $check;
    }

    /** Raven reports no widget resize: sizes are compared after each pump. */
    protected function checkWatched(): void
    {
        if (is_null($this->watched)) {
            return;
        }
        foreach ($this->watched as $primitive => [$read, $changed, $last]) {
            $size = $read();
            if ($size !== $last) {
                $this->watched[$primitive] = [$read, $changed, $size];
                $changed(...$size);
            }
        }
    }

    /**
     * The app's About box: a Raven dialog over $window with the name, version and copyright.
     * While it is open, showing it again does nothing new.
     * @param array{name?: string|null, version?: string|null, copyright?: string|null} $about
     */
    public function showAbout(array $about, int $window): void
    {
        if ($this->about !== 0 && Rwidget_is_valid($this->about) === R_TRUE) {
            return;
        }
        $name = empty($about['name']) ? $this->application_name : (string) $about['name'];
        $content = Rwidget_create(R_WIDGET_VBOX);
        Rpanel_set_spacing($content, 6);
        Rwidget_set_min_size($content, 260, 0);
        $title = Rwidget_add_child($content, Rlabel_create(empty($about['version']) ? $name : "{$name} {$about['version']}"));
        Rlabel_set_font($title, Rfont_get_variant(Rfont_get_default(), R_FONT_BOLD));
        Rlabel_set_text_size($title, 16);
        Rslot_set_halign($title, R_HALIGN_CENTER);
        if (! empty($about['copyright'])) {
            $line = Rwidget_add_child($content, Rlabel_create((string) $about['copyright']));
            Rslot_set_halign($line, R_HALIGN_CENTER);
        }
        $ok = Rwidget_add_child($content, Rbutton_create('OK'));
        Rbutton_set_style($ok, R_BUTTON_PRIMARY);
        Rslot_set_halign($ok, R_HALIGN_RIGHT);
        Rbutton_set_clicked_callback($ok, static fn (int $button) => Rdialog_close($button, 1), null);
        $this->about = $content;
        Rdialog_open($window, $content, function (): void {
            $this->about = 0;
        }, null);
    }

    /** The About dialog's content while it is open, else 0. */
    public function aboutDialog(): int
    {
        return $this->about !== 0 && Rwidget_is_valid($this->about) === R_TRUE ? $this->about : 0;
    }
}
