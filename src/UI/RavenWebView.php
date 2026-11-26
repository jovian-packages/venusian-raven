<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKWebView;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * Raven's system web view (Rsystem_web_view_create), which hosts the system's own engine:
 * WKWebView on macOS. Where Raven has none (every platform but Apple's) supported() is
 * false and topside never calls a load hook.
 *
 * The view is its own native, with no border round it. Raven's event callback reports how a
 * load ended: R_SYSTEM_WEB_VIEW_LOADED is loaded() with the address shown, and
 * R_SYSTEM_WEB_VIEW_FAILED is failed() with Raven's own error text (Rsystem_web_view_get_error).
 * url(), title() and isLoading() read the view back; an empty address or title is null.
 */
class RavenWebView extends TKWebView
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /** The address the last load asked for: Raven's own stays the page before until one shows. */
    private ?string $asked = null;

    /**
     * The callback holds the primitive weakly: a strong capture would be a cycle through
     * ext-raven's held callback that PHP's collector cannot see.
     */
    protected function bootstrapNative(): void
    {
        if (! $this->supported()) {
            // Raven's view would be a placeholder that ends every load with an error: an empty
            // box lays out like any primitive and shows nothing.
            $this->adoptNative(Rwidget_create(R_WIDGET_VBOX), wrapped: false);

            return;
        }

        $this->adoptNative(Rsystem_web_view_create(), wrapped: false);

        $self = WeakReference::create($this);
        Rsystem_web_view_set_event_callback($this->control, static function (int $view, int $event, mixed $data) use ($self): void {
            $web = $self->get();
            if (is_null($web) || $web->isRemoved()) {
                return;
            }

            match ($event) {
                R_SYSTEM_WEB_VIEW_LOADED => $web->loaded($web->url()),
                R_SYSTEM_WEB_VIEW_FAILED => $web->failed($web->asked ?? $web->url(), self::text(Rsystem_web_view_get_error($view))),
                default => null,
            };
        }, null);
    }

    /**
     * The callback goes first: ext-raven would otherwise hold it until it next sweeps.
     */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && $this->supported() && Rwidget_is_valid($this->control) === R_TRUE) {
            Rsystem_web_view_set_event_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }

    public function supported(): bool
    {
        return Rsystem_web_view_is_supported() === R_TRUE;
    }

    public function url(): ?string
    {
        return $this->reading() ? self::text(Rsystem_web_view_get_url($this->control)) : null;
    }

    public function title(): ?string
    {
        return $this->reading() ? self::text(Rsystem_web_view_get_title($this->control)) : null;
    }

    public function isLoading(): bool
    {
        return $this->reading() && Rsystem_web_view_is_loading($this->control) === R_TRUE;
    }

    protected function applyLoad(string $url): void
    {
        $this->asked = $url;
        Rsystem_web_view_load_url($this->control, $url);
    }

    protected function applyLoadHtml(string $html, ?string $baseUrl): void
    {
        $this->asked = $baseUrl;
        Rsystem_web_view_load_html($this->control, $html, $baseUrl);
    }

    protected function applyReload(): void
    {
        Rsystem_web_view_reload($this->control);
    }

    protected function applyStop(): void
    {
        Rsystem_web_view_stop($this->control);
    }

    /**
     * Whether Raven's view is there to ask.
     */
    private function reading(): bool
    {
        return $this->control !== 0 && $this->supported() && Rwidget_is_valid($this->control) === R_TRUE;
    }

    private static function text(?string $text): ?string
    {
        return is_null($text) || $text === '' ? null : $text;
    }
}
