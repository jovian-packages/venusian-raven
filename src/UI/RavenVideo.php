<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKVideo;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A video view on Raven's system web view (Rsystem_web_view_create: WKWebView on macOS)
 * showing one <video> element, Raven having no media widget of its own. The element plays
 * audio files too, and its controls are the web engine's own transport. Where Raven has no
 * web view (every platform but Apple's) supported() is false and topside never calls a hook.
 *
 * The page reports the element's events with window.raven.postMessage (Raven's message
 * callback): playing, pause, ended and error become playing(), paused(), ended() and failed(),
 * and every report carries the position and the duration, which position() and duration()
 * read. A pause the element reports at its end is not a pause: WebKit sends it before ended,
 * with the position already at the duration, so a pause there is dropped. What the
 * view is told goes to the element with Rsystem_web_view_run_javascript once the page has
 * loaded; until then it waits, and the page is built with the muting, looping and controls
 * already set.
 *
 * A stream (http, https) loads as the page's HTML with the stream's origin as its base URL.
 * A file does not play: Raven's web view lets a page read files only in the page's own
 * folder (loadFileURL:allowingReadAccessToURL: on the page's folder; an HTML string has no
 * file access at all), so a file source posts VideoFailed with that reason. Playing local files
 * needs Raven's web view to take the folder a page may read.
 */
class RavenVideo extends TKVideo
{
    public const string NO_FILES = "Raven's web view reads no files outside the page it shows, so it plays streams (http, https) and no local file.";

    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /** The page has loaded: scripts reach the element. */
    private bool $ready = false;

    /** @var list<string> scripts waiting for the page */
    private array $waiting = [];

    private float $at = 0.0;

    private ?float $length = null;

    public function supported(): bool
    {
        return Rsystem_web_view_is_supported() === R_TRUE;
    }

    /**
     * Both callbacks hold the primitive weakly: a strong capture would be a cycle through
     * ext-raven's held callbacks that PHP's collector cannot see.
     */
    protected function bootstrapNative(): void
    {
        if (! $this->supported()) {
            // Raven's view would be a placeholder: an empty box lays out like any primitive.
            $this->adoptNative(Rwidget_create(R_WIDGET_VBOX), wrapped: false);

            return;
        }

        $this->adoptNative(Rsystem_web_view_create(), wrapped: false);

        $self = WeakReference::create($this);
        Rsystem_web_view_set_event_callback($this->control, static function (int $view, int $event, mixed $data) use ($self): void {
            $video = $self->get();
            if (is_null($video) || $video->isRemoved() || is_null($video->source)) {
                return;
            }

            match ($event) {
                R_SYSTEM_WEB_VIEW_LOADED => $video->pageLoaded(),
                R_SYSTEM_WEB_VIEW_FAILED => $video->failed(self::text(Rsystem_web_view_get_error($view))),
                default => null,
            };
        }, null);
        Rsystem_web_view_set_message_callback($this->control, static function (int $view, ?string $message, mixed $data) use ($self): void {
            $video = $self->get();
            if (! is_null($video) && ! $video->isRemoved() && ! is_null($video->source)) {
                $video->heard($message);
            }
        }, null);
    }

    /**
     * The callbacks go first: ext-raven would otherwise hold them until it next sweeps.
     */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && $this->supported() && Rwidget_is_valid($this->control) === R_TRUE) {
            Rsystem_web_view_set_event_callback($this->control, null, null);
            Rsystem_web_view_set_message_callback($this->control, null, null);
            Rsystem_web_view_stop($this->control);
            Rsystem_web_view_load_html($this->control, '', null);
        }

        $this->releaseWidget();
    }

    private function pageLoaded(): void
    {
        $this->ready = true;
        [$scripts, $this->waiting] = [$this->waiting, []];
        foreach ($scripts as $script) {
            Rsystem_web_view_run_javascript($this->control, $script, null, null);
        }
    }

    /**
     * One report from the page: {"event": ..., "position": s, "duration": s|null, "error": ...}.
     * Anything else is not from the page this view built, and is dropped.
     */
    private function heard(?string $message): void
    {
        $report = is_null($message) ? null : json_decode($message, true);
        if (! is_array($report) || ! is_string($report['event'] ?? null)) {
            return;
        }

        if (is_int($report['position'] ?? null) || is_float($report['position'] ?? null)) {
            $this->at = max(0.0, (float) $report['position']);
        }
        $duration = $report['duration'] ?? null;
        $this->length = (is_int($duration) || is_float($duration)) && $duration > 0 ? (float) $duration : null;

        match ($report['event']) {
            'playing' => $this->playing(),
            'pause' => $this->paused(),
            'ended' => $this->ended(),
            'error' => $this->failed(is_string($report['error'] ?? null) && $report['error'] !== '' ? $report['error'] : null),
            default => null,
        };
    }

    /** Run $script on the element now, or once the page has loaded. */
    private function tell(string $script): void
    {
        if ($this->ready) {
            Rsystem_web_view_run_javascript($this->control, $script, null, null);
        } else {
            $this->waiting[] = $script;
        }
    }

    protected function applySource(?string $url): void
    {
        $this->ready = false;
        $this->waiting = [];
        $this->at = 0.0;
        $this->length = null;

        if (is_null($url) || ! preg_match('#^https?://#i', $url)) {
            Rsystem_web_view_load_html($this->control, '', null);
            is_null($url) || $this->failed(self::NO_FILES);

            return;
        }

        Rsystem_web_view_load_html($this->control, $this->page($url), self::originOf($url));
    }

    /**
     * The page: the element filling a black page, the picture whole, and the script that
     * reports its events.
     */
    private function page(string $url): string
    {
        $src = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5);
        $flags = implode(' ', array_keys(array_filter(['controls' => $this->controls, 'muted' => $this->muted, 'loop' => $this->looping])));

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'html,body{margin:0;height:100%;overflow:hidden;background:#000}'
            .'video{display:block;width:100%;height:100%;object-fit:contain}'
            .'</style></head><body>'
            ."<video id=\"player\" src=\"{$src}\" preload=\"metadata\" playsinline {$flags}></video>"
            .'<script>(function(){var v=document.getElementById("player");'
            .'function say(e,x){var d=v.duration;window.raven.postMessage(JSON.stringify({event:e,position:v.currentTime,duration:isFinite(d)?d:null,error:x||null}));}'
            .'["playing","ended","loadedmetadata","durationchange","timeupdate","seeked"].forEach(function(e){v.addEventListener(e,function(){say(e);});});'
            .'v.addEventListener("pause",function(){if(!v.ended&&!(v.currentTime>=v.duration)){say("pause");}});'
            .'v.addEventListener("error",function(){var m=v.error;say("error",m?(m.message||("Media error "+m.code+".")):null);});'
            .'})();</script></body></html>';
    }

    /** A stream's origin: what the page resolves against, and the origin it has. */
    private static function originOf(string $url): ?string
    {
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/'
            : null;
    }

    protected function applyPlay(): void
    {
        $this->tell('document.getElementById("player").play().catch(function(){});');
    }

    protected function applyPause(): void
    {
        $this->tell('document.getElementById("player").pause();');
    }

    protected function applySeek(float $seconds): void
    {
        $this->at = $seconds;
        $this->tell('document.getElementById("player").currentTime='.json_encode($seconds).';');
    }

    protected function applyMuted(bool $muted): void
    {
        $this->tell('document.getElementById("player").muted='.($muted ? 'true' : 'false').';');
    }

    protected function applyLoop(bool $loop): void
    {
        $this->tell('document.getElementById("player").loop='.($loop ? 'true' : 'false').';');
    }

    protected function applyControls(bool $controls): void
    {
        $this->tell('document.getElementById("player").controls='.($controls ? 'true' : 'false').';');
    }

    protected function readPosition(): float
    {
        return $this->at;
    }

    protected function readDuration(): ?float
    {
        return $this->length;
    }

    private static function text(?string $text): ?string
    {
        return is_null($text) || $text === '' ? null : $text;
    }
}
