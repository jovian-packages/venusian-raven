<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKLink;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Jovian\Toolkits\Raven\UI\Concerns\RavenText;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Markdown label holding one link, the text escaped. Raven draws it as a link and calls
 * back on a click, which opens the URL with Ropen_url.
 */
class RavenLink extends TKLink
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }
    use RavenText;

    protected function bootstrapNative(): void
    {
        $label = Rlabel_create($this->markdown());
        Rlabel_set_markdown($label, R_TRUE);
        // Markdown wraps at the label's width: a link is as wide as its text.
        Rlabel_set_wrap($label, R_FALSE);
        $this->adoptNative($label);
        $this->applyAlignment($this->alignment);

        $self = WeakReference::create($this);
        Rlabel_set_link_callback($label, static function () use ($self): void {
            $link = $self->get();
            if (! is_null($link) && ! $link->isRemoved() && $link->takesInput()) {
                Ropen_url($link->url());
            }
        }, null);
    }

    protected function applyText(string $text): void
    {
        Rlabel_set_text($this->control, $this->markdown());
    }

    protected function applyUrl(string $url): void
    {
        Rlabel_set_text($this->control, $this->markdown());
    }

    protected function applyAlignment(TextAlignment $alignment): void
    {
        Rlabel_set_text_align($this->control, self::ravenAlignment($alignment));
    }

    protected function applyFont(FontSpec $font): void
    {
        Rlabel_set_font($this->control, self::ravenFont($font));
        Rlabel_set_text_size($this->control, $font->size);
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rlabel_set_link_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }

    private function markdown(): string
    {
        $text = preg_replace('/([\\\\`*_{}\[\]()#+\-.!<>|~])/', '\\\\$1', $this->text());
        $text = str_replace(["\r\n", "\r", "\n"], "  \n", $text);

        return "[{$text}](".str_replace(['(', ')'], ['%28', '%29'], $this->url()).')';
    }
}
