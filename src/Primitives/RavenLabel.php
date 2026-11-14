<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenFonts;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Primitives\TKLabel;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;

class RavenLabel extends TKLabel implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $text)
    {
        parent::__construct($name, $window, $parent, $placement, $text);
        $this->adoptNative(Rlabel_create($text));
    }

    protected function applyText(string $text): void
    {
        Rlabel_set_text($this->control, $text);
    }

    protected function applyWrap(bool $wrap): void
    {
        Rlabel_set_wrap($this->control, $wrap ? R_TRUE : R_FALSE);
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

    protected function applyTextColor(?Color $color): void
    {
        is_null($color)
            ? Rwidget_clear_color($this->control, R_COLOR_TEXT)
            : Rlabel_set_color($this->control, $color->red, $color->green, $color->blue, $color->alpha);
    }
}
