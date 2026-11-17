<?php

declare(strict_types=1);

/*
 * The human-input tests run in a real Venusian application: TOPSIDE_APP names one that requires
 * topside and this package (apps/topside-dev), booted once with Raven as its toolkit and the
 * 'raven' input backend. Run them with that application's Pest and autoloader:
 *
 *     TOPSIDE_APP=…/apps/topside-dev zhp …/apps/topside-dev/vendor/bin/pest \
 *         --bootstrap=…/apps/topside-dev/vendor/autoload.php tests/HumanInput
 *
 * Raven runs on its host platform (raven_host.h) with the null renderer: it opens no OS window, and
 * input is handed to it with Rhost_inject_*, which delivers each event through the window's event
 * hook as an OS event would. Each window is a 600x400 pixel surface at scale 2: 300x200 logical
 * units, as with a real window on a display of twice the density.
 */

use Jovian\Toolkits\Raven\Input\RavenInputBackend;
use Jovian\Toolkits\Raven\Primitives\RavenCanvas;
use Jovian\Toolkits\Raven\RavenWindow;
use Surface\Contracts\HumanInput\InputContext;
use Surface\HumanInput\HumanInput;
use Surface\HumanInput\HumanInputManager;
use Surface\Toolkits\Toolkits;
use Voyager\Contracts\Core\Kernel;

/** Mail the manager sent: tests read and clear it. */
final class RiMail
{
    /** @var list<object> */
    public static array $sent = [];
}

/** The application, booted once with Raven on its host platform and the 'raven' backend. */
function riApp(): object
{
    static $app = null;
    if (! is_null($app)) {
        return $app;
    }

    $base = getenv('TOPSIDE_APP');
    if ($base === false || ! is_file("{$base}/bootstrap/main.php")) {
        throw new RuntimeException('Set TOPSIDE_APP to a Venusian application that requires venusian/topside and this package, such as apps/topside-dev.');
    }
    $env = [
        'PRESENTATION_TOOLKIT_DRIVER' => 'raven', 'TOOLKIT_BRIDGE_DEFAULT' => 'raven',
        'HUMAN_INPUT' => 'true', 'HUMAN_INPUT_BACKEND' => 'raven',
        'RAVEN_HEADLESS' => 'false',
    ];
    foreach ($env as $name => $value) {
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
    // before Raven starts: windows are the application's surfaces, their input injected
    Rhost_platform_register(new RHostPlatformDesc());

    $app = require "{$base}/bootstrap/main.php";
    $app->make(Kernel::class)->bootstrap();
    // the null renderer: env() reads RAVEN_BACKEND=null as no value at all
    $app['config']->set('raven.backend', 'null');

    return $app;
}

/** The manager with a fresh 'raven' backend, resolved and listening. The one the application booted with is let go first. */
function riInput(): HumanInputManager
{
    riApp();
    $manager = HumanInput::manager();
    $manager->backend()?->disconnect();
    $manager->forgetDrivers();
    RiMail::$sent = [];

    $manager->extend('raven', fn (): RavenInputBackend => new RavenInputBackend());
    $manager->mailTo(static function (object $mail): void {
        RiMail::$sent[] = $mail;
    });
    $manager->resolve(new InputContext('raven', 'velvet', PHP_OS_FAMILY === 'Darwin' ? 'mac' : 'linux'));
    $manager->connect();

    return $manager;
}

/**
 * A 300x200 window with two canvases stacked, each 300x100 logical units: 'top' and 'bottom'. Laid
 * out on a 600x400 pixel surface at scale 2, and given the keyboard.
 *
 * @return array{RavenWindow, RavenCanvas, RavenCanvas}
 */
function riWindow(string $name = 'ri'): array
{
    $window = Toolkits::window()->open($name, 300, 200);
    $column = $window->column('m');
    $column->fill();
    $top = $column->canvas('top');
    $top->fill();
    $bottom = $column->canvas('bottom');
    $bottom->fill();
    Rhost_window_set_size($window->rid(), 600, 400, 2.0);
    Rhost_inject_focus($window->rid(), R_TRUE);
    Rdraw_frame();

    return [$window, $top, $bottom];
}

function riCloseWindows(): void
{
    riApp();
    Toolkits::window()->closeAll();
}

/** Where a canvas is in its window, in logical units: x, y, width, height. */
function riRect(RavenCanvas $canvas): array
{
    Rwidget_get_rect($canvas->control(), $x, $y, $width, $height);

    return [$x, $y, $width, $height];
}

/** Runs $inject (which calls Rhost_inject_* on the window), then polls. */
function riDeliver(HumanInputManager $input, Closure $inject): void
{
    $inject();
    $input->poll();
}

/** @return list<object> the mail sent since the last call */
function riMailSent(): array
{
    [$mail, RiMail::$sent] = [RiMail::$sent, []];

    return $mail;
}
