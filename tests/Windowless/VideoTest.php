<?php

declare(strict_types=1);

use Jovian\Toolkits\Raven\UI\RavenVideo;
use Surface\Toolkits\ToolkitSession;
use Surface\Contracts\Toolkits\Mail\Views\VideoEnded;
use Surface\Contracts\Toolkits\Mail\Views\VideoFailed;
use Surface\Contracts\Toolkits\Mail\Views\VideoPaused;
use Surface\Contracts\Toolkits\Mail\Views\VideoPlaying;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php and
| LabelTruncateTest.php. A video is Raven's system web view (WKWebView on macOS) showing one
| <video> element; its events come back through window.raven.postMessage. The null renderer
| has no system web view, so there only the no-player path runs and the player cases skip;
| they run with Raven's real backend. Raven's web view plays streams, so the fixtures (a 1.2 s
| 160 x 120 H.264 clip and a 1 s AAC tone) come from PHP's built-in server on 127.0.0.1, with
| byte ranges (fixtures/serve.php).
*/

/** The video mail the session holds, taken out. @return list<object> */
function ravenVideoMail(array $menus): array
{
    [, , $session] = $menus;
    $mail = Closure::bind(function (): array {
        [$mail, $this->outbox] = [$this->outbox, []];

        return $mail;
    }, $session, ToolkitSession::class)();

    return array_values(array_filter($mail, fn (object $m): bool => str_starts_with($m::class, 'Surface\Contracts\Toolkits\Mail\Views\Video')));
}

/** Frames until $done() holds or $seconds pass, collecting the video mail. @return list<object> */
function ravenVideoUntil(array $menus, object $window, Closure $done, float $seconds = 10.0): array
{
    $mail = [];
    $until = microtime(true) + $seconds;
    do {
        Rwait_frame(0.01);
        $window->settle();
        array_push($mail, ...ravenVideoMail($menus));
    } while (! $done($mail) && microtime(true) < $until);

    return $mail;
}

/** The fixture's address on a server started once for the process. */
function ravenVideoFixture(string $name): string
{
    static $port = null;
    if (is_null($port)) {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $dir = dirname(__DIR__).'/fixtures';
        $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", "{$dir}/serve.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $dir);
        register_shutdown_function(static fn () => proc_terminate($server));
        // Until it answers: a refused connection while it starts is expected, not a warning.
        set_error_handler(static fn (): bool => true);
        $until = microtime(true) + 5.0;
        while (! stream_socket_client("tcp://127.0.0.1:{$port}", $code, $text, 0.1) && microtime(true) < $until) {
            usleep(20_000);
        }
        restore_error_handler();
    }

    return "http://127.0.0.1:{$port}/{$name}";
}

/** @return array{array, object, RavenVideo} */
function ravenVideo(object $test, bool $player = true): array
{
    $menus = ravenMenus();
    $test->opened[] = $window = ravenWindow($menus);
    /** @var RavenVideo $video */
    $video = $window->column('m')->video('player')->fill();
    laidOut($window);
    ravenVideoMail($menus);

    if ($player && ! $video->supported()) {
        test()->markTestSkipped('This Raven has no system web view (the null renderer has none).');
    }

    return [$menus, $window, $video];
}

$kinds = fn (array $mail): array => array_map(fn (object $m): string => (new ReflectionClass($m))->getShortName(), $mail);

it('is Raven\'s system web view where Raven has one, and an empty box where it has none', function (): void {
    [, , $video] = ravenVideo($this, player: false);

    expect($video->supported())->toBe(Rsystem_web_view_is_supported() === R_TRUE)
        ->and(Rwidget_get_type($video->native()))->toBe($video->supported() ? R_WIDGET_SYSTEM_WEB_VIEW : R_WIDGET_VBOX);
});

it('with no system web view: every source posts VideoFailed with the reason, and nothing plays', function () use ($kinds): void {
    [$menus, $window, $video] = ravenVideo($this, player: false);
    if ($video->supported()) {
        test()->markTestSkipped('This Raven has a system web view.');
    }
    $video->setControls(true)->setSource(ravenVideoFixture('clip.mp4'))->play();
    $mail = ravenVideoMail($menus);

    expect($kinds($mail))->toBe(['VideoFailed'])
        ->and($mail[0]->reason)->toBe(\Surface\Toolkits\UI\TKVideo::NO_PLAYER)
        ->and($video->isPlaying())->toBeFalse();
});

it('loads a file, reads its duration, plays it to the end, and is not playing after', function () use ($kinds): void {
    [$menus, $window, $video] = ravenVideo($this);
    $video->setMuted(true)->setSource(ravenVideoFixture('clip.mp4'));
    ravenVideoUntil($menus, $window, fn (): bool => ! is_null($video->duration()));
    $duration = $video->duration();
    $video->play();
    $mail = ravenVideoUntil($menus, $window, fn (array $mail): bool => in_array('VideoEnded', $kinds($mail), true));

    expect($duration)->toEqualWithDelta(1.2, 0.1)
        ->and($kinds($mail))->toBe(['VideoPlaying', 'VideoEnded'])
        ->and($video->isPlaying())->toBeFalse();
});

it('posts VideoPaused on pause, and seeks', function () use ($kinds): void {
    [$menus, $window, $video] = ravenVideo($this);
    $video->setMuted(true)->setSource(ravenVideoFixture('tone.m4a'))->play();
    ravenVideoUntil($menus, $window, fn (array $mail): bool => in_array('VideoPlaying', $kinds($mail), true));
    $video->pause();
    $paused = ravenVideoUntil($menus, $window, fn (array $mail): bool => in_array('VideoPaused', $kinds($mail), true));
    $video->seek(0.5);
    ravenVideoUntil($menus, $window, fn (): bool => false, 0.5);

    expect($kinds($paused))->toBe(['VideoPaused'])
        ->and($video->position())->toEqualWithDelta(0.5, 0.05);
});

it('posts VideoFailed with the engine\'s message for a stream it cannot play', function () use ($kinds): void {
    [$menus, $window, $video] = ravenVideo($this);
    $video->setSource(ravenVideoFixture('missing.mp4'));
    $mail = ravenVideoUntil($menus, $window, fn (array $mail): bool => in_array('VideoFailed', $kinds($mail), true));
    $failed = array_values(array_filter($mail, fn (object $m): bool => $m instanceof VideoFailed));

    expect($failed)->toHaveCount(1)
        ->and($failed[0]->source)->toBe(ravenVideoFixture('missing.mp4'))
        ->and($failed[0]->reason)->not->toBeEmpty();
});

it('posts VideoFailed for a file, which Raven\'s web view cannot read', function () use ($kinds): void {
    [$menus, $window, $video] = ravenVideo($this);
    $video->setSource(dirname(__DIR__).'/fixtures/clip.mp4');
    $mail = ravenVideoMail($menus);

    expect($kinds($mail))->toBe(['VideoFailed'])
        ->and($mail[0]->reason)->toBe(RavenVideo::NO_FILES);
});

it('posts nothing from a removed video', function () use ($kinds): void {
    [$menus, $window, $video] = ravenVideo($this);
    $video->setMuted(true)->setSource(ravenVideoFixture('clip.mp4'))->play();
    $video->remove();
    $mail = ravenVideoUntil($menus, $window, fn (): bool => false, 1.5);

    expect($mail)->toBe([]);
});

it('pauses when it stops being shown, as a container above it hides', function () use ($kinds): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $drawer = $window->column('m')->column('drawer')->fill();
    /** @var RavenVideo $video */
    $video = $drawer->video('player')->fill();
    laidOut($window);
    ravenVideoMail($menus);
    if (! $video->supported()) {
        test()->markTestSkipped('This Raven has no system web view (the null renderer has none).');
    }
    $video->setMuted(true)->setLoop(true)->setSource(ravenVideoFixture('clip.mp4'))->play();
    ravenVideoUntil($menus, $window, fn (array $mail): bool => in_array('VideoPlaying', $kinds($mail), true));
    $drawer->hide();
    $mail = ravenVideoUntil($menus, $window, fn (array $mail): bool => in_array('VideoPaused', $kinds($mail), true), 3.0);

    expect($kinds($mail))->toBe(['VideoPaused'])
        ->and($video->isPlaying())->toBeFalse();
});
