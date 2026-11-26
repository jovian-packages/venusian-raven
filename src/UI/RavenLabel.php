<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\NutsAndBolts\Color;
use Surface\Toolkits\UI\TKLabel;
use Surface\Contracts\Toolkits\Styling\TextRun;
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
 *
 * Runs are Raven's Markdown label (Rlabel_set_markdown): a bold run is **strong**, an italic
 * one *emphasis*, drawn in the bold and italic variants of the label's font, at its size and
 * in its colour. Each run's text is escaped, so Markdown reads nothing else in it: every ASCII
 * punctuation mark is escaped (no titles, lists, links, code or bare addresses), a line break
 * is a backslash break, an empty line holds a no-break space, and spaces Markdown would fold
 * (at a line's start, after another space) are no-break spaces. Emphasis takes no space at its
 * edges, so a run's leading and trailing spaces stay outside its marks. A run's colour is not
 * shown: Raven's Markdown colours text, links, quotes and code only, and its label has no
 * other way to colour part of its text. Runs that are all plain are the plain label. Raven's
 * Markdown places its block by the alignment, not each line, so wrapped lines of runs start
 * at the left of the block. setText() turns Markdown off again.
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
        // Wrap chosen from the start: Raven wraps a Markdown label unless told not to.
        Rlabel_set_wrap($this->control, R_FALSE);
        $this->applyAlignment($this->alignment);
    }

    protected function applyText(string $text): void
    {
        Rlabel_set_markdown($this->control, R_FALSE);
        Rlabel_set_text($this->control, $text);
    }

    /**
     * @param list<TextRun> $runs
     */
    protected function applyRuns(array $runs): void
    {
        if (array_filter($runs, static fn (TextRun $run): bool => ! $run->bold && ! $run->italic) === $runs) {
            $this->applyText(TextRun::join($runs));

            return;
        }

        Rlabel_set_markdown($this->control, R_TRUE);
        Rlabel_set_text($this->control, self::markdown($runs));
    }

    /**
     * @param list<TextRun> $runs
     */
    private static function markdown(array $runs): string
    {
        $out = '';
        $start = true;
        foreach ($runs as $run) {
            $marks = ($run->bold ? '**' : '').($run->italic ? '*' : '');
            foreach (preg_split('/\r\n|\r|\n/', $run->text) as $at => $line) {
                if ($at > 0) {
                    $out .= "\\\n";
                    $start = true;
                }
                if ($line === '') {
                    continue;
                }
                if ($marks === '' || trim($line) === '') {
                    $out .= self::escape($line, $start);
                } else {
                    preg_match('/^(\s*)(.*?)(\s*)$/su', $line, $parts);
                    $out .= self::escape($parts[1], $start).$marks.self::escape($parts[2], false).$marks.self::escape($parts[3], false);
                }
                $start = false;
            }
        }

        // An empty line between two breaks holds a no-break space, or Markdown ends the paragraph.
        return preg_replace('/(^|\\\n)(?=\\\n|$)/', '$1&nbsp;', $out) ?? $out;
    }

    /**
     * Every ASCII punctuation mark escaped; a space Markdown would fold is a no-break space.
     */
    private static function escape(string $text, bool $lineStart): string
    {
        $text = (string) preg_replace('/([!-\/:-@\[-`{-~])/', '\\\\$1', $text);
        $text = (string) preg_replace('/(?<= ) /', '&nbsp;', $text);

        return $lineStart ? (string) preg_replace_callback('/^ +/', static fn (array $m): string => str_repeat('&nbsp;', strlen($m[0])), $text) : $text;
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
