<?php

namespace Jovian\Toolkits\Raven;

use Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException;
use Jovian\Toolkits\Raven\Services\RavenClipboard;
use Jovian\Toolkits\Raven\Services\RavenDesktop;
use Jovian\Toolkits\Raven\Services\RavenDialogs;
use Jovian\Toolkits\Raven\Services\RavenSystemAccent;
use ReflectionException;
use Surface\Contracts\Toolkits\Drivers\ToolkitWindowDriver;
use Surface\Contracts\Toolkits\Menus\MenuProfile as MenuProfileContract;
use Surface\Contracts\Toolkits\Styling\FontMetrics;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Menus\MenuProfile;
use Surface\Toolkits\ToolkitBridge;
use Surface\Toolkits\Windows\ChildWindows;

class RavenBridge extends ToolkitBridge implements ToolkitWindowDriver
{
    /**
     * Open windows by name; a window leaves when it closes.
     * @var array<string, RavenWindow>
     */
    protected array $windows = [];

    protected ?RavenDialogs $dialogs = null;

    protected ?RavenClipboard $clipboard = null;

    protected ?RavenDesktop $desktop = null;

    protected ?RavenSystemAccent $systemAccent = null;

    protected string $session_class = RavenSession::class;

    /**
     * @throws ReflectionException
     */
    protected function instanceSession(): void
    {
        $this->session ??= new RavenSession(
            (string) config('app.name', 'Venusian'),
            (string) config('raven.backend', env('RAVEN_BACKEND', 'vulkan')),
            (bool) config('raven.headless', (bool) env('RAVEN_HEADLESS', false)),
            (float) config('raven.ui_scale', (float) env('RAVEN_UI_SCALE', 0.0)),
            config('raven.theme', env('RAVEN_THEME')) ?: null,
        );
    }

    protected function connected(): void
    {
        /** @var RavenSession $session */
        $session = $this->session;
        // Compared after every pump from the start, not from the app's first clipboard call.
        $this->clipboard ??= new RavenClipboard($session);
    }

    /** With a parent, the window is a Raven dialog inside the parent: modal to it. */
    public function open(string $name, int $width, int $height, ?MenuProfileContract $menu = null, ?ToolkitWindow $parent = null): RavenWindow
    {
        if (isset($this->windows[$name])) {
            throw ToolkitRuntimeException::instanceOpen($name);
        }

        if (! is_null($parent)) {
            ChildWindows::check($this, $parent, $menu);
            $this->dialogs()->refuseChild($parent);
        }

        /** @var RavenSession $session */
        $session = $this->connect();

        return $this->windows[$name] = new RavenWindow($name, $session, $this, $width, $height, $menu, $parent);
    }

    public function has(string $name): bool
    {
        return isset($this->windows[$name]);
    }

    public function get(string $name): ?RavenWindow
    {
        return $this->windows[$name] ?? null;
    }

    /** @return array<string, RavenWindow> */
    public function all(): array
    {
        return $this->windows;
    }

    public function closeAll(): void
    {
        foreach ($this->windows as $window) {
            $window->close();
        }
    }

    /** A window closed: it leaves the open windows. */
    public function forget(string $name): void
    {
        unset($this->windows[$name]);
    }

    /** Alerts inside their parent window, and the platform's file panels. */
    public function dialogs(): RavenDialogs
    {
        /** @var RavenSession $session */
        $session = $this->connect();

        return $this->dialogs ??= new RavenDialogs($this, $session);
    }

    public function clipboard(): RavenClipboard
    {
        $this->connect();

        return $this->clipboard;
    }

    public function desktop(): RavenDesktop
    {
        return $this->desktop ??= new RavenDesktop();
    }

    /** Raven's theme answers the accent: the session connects first. */
    public function systemAccent(): RavenSystemAccent
    {
        $this->connect();

        return $this->systemAccent ??= new RavenSystemAccent();
    }

    /**
     * @throws RavenIncompatibilityException Always: Raven measures text but reports no ascent, descent or pitch.
     */
    public function fontMetrics(FontSpec $font): FontMetrics
    {
        throw RavenIncompatibilityException::feature('Font metrics', 'Raven measures a text\'s width and height (Rfont_measure_text) but reports no ascent, descent or fixed pitch.');
    }

    /**
     * A profile from config/windows.php, through the window manager.
     */
    public function profile(string $name): MenuProfile
    {
        return $this->app->get('toolkit-windows')->profile($name);
    }

    /**
     * The About identity from config('windows.about').
     * @return array{name?: string|null, version?: string|null, copyright?: string|null}
     */
    public function about(): array
    {
        return (array) $this->app->get('config')->get('windows.about', []);
    }
}
