<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\NutsAndBolts\Color;
use Surface\Toolkits\UI\TKLabel;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Jovian\Toolkits\Raven\UI\Concerns\RavenText;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven label in a border (the border paints the background).
 *
 * Fonts: Raven embeds Roboto and Roboto Mono and loads any other font from a file only, so a
 * family is found by its .ttf or .otf file in the system font folders. A family with no such
 * file falls back to Raven's default font, as GTK and Qt fall back when a family is not
 * installed; its weight and italic still apply. A Raven font has a regular, a bold, an italic
 * and a bold italic style and no other weights: LIGHT and MEDIUM are drawn regular, SEMIBOLD,
 * BOLD and BLACK bold. Without a file of its own for a style,
 * Raven draws the glyphs thicker or slanted, keeping their advances.
 */
class RavenLabel extends TKLabel
{
    use RavenNative;
    use RavenText;

    /**
     * Raven's label is left-aligned, as topside's is until set; set anyway, from the start.
     */
    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rlabel_create($this->text()));
        $this->applyAlignment($this->alignment);
    }

    protected function applyText(string $text): void
    {
        Rlabel_set_text($this->control, $text);
    }

    /**
     * True wraps on the width the parent gives the label.
     */
    protected function applyWrap(bool $wrap): void
    {
        Rlabel_set_wrap($this->control, $wrap ? R_TRUE : R_FALSE);
    }

    protected function applyAlignment(TextAlignment $alignment): void
    {
        Rlabel_set_text_align($this->control, self::ravenAlignment($alignment));
    }

    /**
     * The size is in Raven's logical units, the points FontSpec sizes are in.
     */
    protected function applyFont(FontSpec $font): void
    {
        Rlabel_set_font($this->control, self::ravenFont($font));
        Rlabel_set_text_size($this->control, $font->size);
    }

    /**
     * The label's own text colour; null goes back to the theme's.
     */
    protected function applyTextColor(?Color $color): void
    {
        is_null($color)
            ? Rwidget_clear_color($this->control, R_COLOR_TEXT)
            : Rlabel_set_color($this->control, $color->red, $color->green, $color->blue, $color->alpha);
    }
}
