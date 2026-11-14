<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenFonts;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKLink;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;

/**
 * A Markdown label holding one link: Raven draws it as a link and calls back on a click, which
 * opens the address with Ropen_url.
 */
class RavenLink extends TKLink implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $text, string $url)
    {
        parent::__construct($name, $window, $parent, $placement, $text, $url);
        $label = $this->adoptNative(Rlabel_create(self::markdown($text, $url)));
        Rlabel_set_markdown($label, R_TRUE);
        $self = WeakReference::create($this);
        Rlabel_set_link_callback($label, static function (int $label, ?string $url) use ($self): void {
            if (($self->get()?->handling() ?? false) && ! is_null($url)) {
                Ropen_url($url);
            }
        }, null);
        $this->applyAlignment($this->alignment);
    }

    /** The text as Markdown link text, every Markdown character escaped. */
    public static function markdown(string $text, string $url): string
    {
        $escaped = preg_replace('/([\\\\`*_{}\[\]()#+\-.!<>|~])/', '\\\\$1', $text);

        return '['.str_replace(["\r\n", "\r", "\n"], '  '."\n", $escaped).']('.str_replace([' ', ')'], ['%20', '%29'], $url).')';
    }

    protected function applyText(string $text): void
    {
        Rlabel_set_text($this->control, self::markdown($text, $this->url));
    }

    protected function applyUrl(string $url): void
    {
        Rlabel_set_text($this->control, self::markdown($this->text, $url));
    }

    protected function applyAlignment(TextAlignment $alignment): void
    {
        Rlabel_set_text_align($this->control, self::textAlign($alignment));
    }

    protected function applyFont(FontSpec $font): void
    {
        Rlabel_set_font($this->control, RavenFonts::font($font));
        Rlabel_set_text_size($this->control, $font->size);
    }
}
