<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\NutsAndBolts\Color;
use Surface\Toolkits\UI\TKFixed;
use Surface\Toolkits\UI\TKLabel;
use Surface\Toolkits\UI\TKOverlay;
use Jovian\Toolkits\Raven\RavenWindow;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\Styling\FontWeight;
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
 *
 * Truncating: Raven's label has no ellipsis, so the label shows its text cut to its width with
 * "…", measured with Rfont_measure_text() in the label's font and size (runs each in their own
 * bold or italic variant, the run that does not fit cut and the rest dropped). Its desired
 * width is capped at 0 (Rwidget_set_max_size) and its slot is FILL across, so it takes the
 * room it is given and asks for none: a long text never widens what holds it. Raven reports no
 * resize of a widget, so the window cuts it again after each pump when its width changed
 * (RavenWindow::settle()). It is one line whatever wrap says.
 */
class RavenLabel extends TKLabel
{
    use RavenNative {
        sizeNative as private ownSize;
        slotAligned as private ownSlot;
    }
    use RavenText;

    /** The font set: the cut measures in it. Null is Raven's default font at the theme's size. */
    private ?FontSpec $font_spec = null;

    /** @var list<TextRun>|null the runs shown; null while the text is plain */
    private ?array $shown_runs = null;

    /** The width the text was last cut to; null while it is whole. */
    private ?float $cut_width = null;

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
        $this->shown_runs = null;
        $this->showText();
    }

    /**
     * @param list<TextRun> $runs
     */
    protected function applyRuns(array $runs): void
    {
        $this->shown_runs = array_filter($runs, static fn (TextRun $run): bool => ! $run->bold && ! $run->italic) === $runs ? null : $runs;
        $this->showText();
    }

    /**
     * The text, or the runs, whole; or, truncating, cut to the label's width once it has one.
     */
    private function showText(): void
    {
        $width = $this->truncates() ? $this->room() : 0.0;
        $this->cut_width = $width > 0.0 ? $width : null;
        [$text, $runs] = is_null($this->cut_width) ? [$this->text(), $this->shown_runs] : $this->cutTo($this->cut_width);

        if (is_null($runs)) {
            Rlabel_set_markdown($this->control, R_FALSE);
            Rlabel_set_text($this->control, $text);

            return;
        }

        Rlabel_set_markdown($this->control, R_TRUE);
        Rlabel_set_text($this->control, self::markdown($runs));
    }

    /**
     * After a pump (RavenWindow::settle()): cut again when the label's width changed.
     *
     * @internal RavenWindow
     */
    public function recut(): void
    {
        if ($this->isRemoved() || ! $this->truncates()) {
            return;
        }

        $width = $this->room();
        if ($width > 0.0 && (is_null($this->cut_width) || abs($width - $this->cut_width) >= 0.5)) {
            $this->showText();
        }
    }

    /** The width the label has, as Raven laid it out; 0 before it is. */
    private function room(): float
    {
        Rwidget_get_rect($this->control, $x, $y, $width, $height);

        return max(0.0, (float) $width);
    }

    /**
     * The text cut to $width, or the runs cut to it run by run.
     *
     * @return array{string, ?list<TextRun>}
     */
    private function cutTo(float $width): array
    {
        if (is_null($this->shown_runs)) {
            return [$this->fitted($this->text(), $width, false, false), null];
        }

        $runs = [];
        $left = $width;
        foreach ($this->shown_runs as $run) {
            $advance = $this->advance($run->text, $run->bold, $run->italic);
            if ($advance <= $left) {
                $runs[] = $run;
                $left -= $advance;
                continue;
            }

            $runs[] = new TextRun($this->fitted($run->text, $left, $run->bold, $run->italic), bold: $run->bold, italic: $run->italic, color: $run->color);
            break;
        }

        return ['', $runs];
    }

    /**
     * $text whole when it fits in $width; else its longest start that fits with "…" after it
     * (the ellipsis alone when none does), found by halving.
     */
    private function fitted(string $text, float $width, bool $bold, bool $italic): string
    {
        if ($this->advance($text, $bold, $italic) <= $width) {
            return $text;
        }

        [$low, $high] = [0, mb_strlen($text)];
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            if ($this->advance(rtrim(mb_substr($text, 0, $mid)).'…', $bold, $italic) <= $width) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return rtrim(mb_substr($text, 0, $low)).'…';
    }

    /**
     * The width of $text in the label's font and size, made bold or italic as a run asks.
     */
    private function advance(string $text, bool $bold, bool $italic): float
    {
        $spec = $this->font_spec;
        if (is_null($spec)) {
            // Raven's default font at size 0: the theme's size, as the label draws it.
            $style = match (true) {
                $bold && $italic => R_FONT_BOLD_ITALIC,
                $bold => R_FONT_BOLD,
                $italic => R_FONT_ITALIC,
                default => R_FONT_REGULAR,
            };
            $font = $style === R_FONT_REGULAR ? Rfont_get_default() : Rfont_get_variant(Rfont_get_default(), $style);
            Rfont_measure_text($font, 0.0, $text, $width, $height);

            return (float) $width;
        }

        $font = self::ravenFont(new FontSpec($spec->size, $bold ? FontWeight::BOLD : $spec->weight, $spec->family, $spec->monospace, $italic || $spec->italic));
        Rfont_measure_text($font, $spec->size, $text, $width, $height);

        return (float) $width;
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
     * True wraps on the width the parent gives the label; not while it truncates.
     */
    protected function applyWrap(bool $wrap): void
    {
        Rlabel_set_wrap($this->control, $wrap && ! $this->truncates() ? R_TRUE : R_FALSE);
    }

    protected function applyTruncate(bool $truncate): void
    {
        Rlabel_set_wrap($this->control, $this->wraps() && ! $truncate ? R_TRUE : R_FALSE);
        $window = $this->window();
        if ($window instanceof RavenWindow) {
            $truncate ? $window->watchWidth($this) : $window->unwatchWidth($this);
        }
        $this->sizeNative();
        if (! $this->parent() instanceof TKFixed) {
            self::placeAlignment($this->native, $this->slotAligned());
        }
        $this->showText();
    }

    /**
     * Truncating with no ceiling: the desired width is capped at 0, so the label asks for no
     * width and its FILL slot gives it its room.
     */
    public function sizeNative(): void
    {
        $this->ownSize();
        [$maxWidth, $maxHeight] = $this->maximumSize();
        if ($this->native === 0 || ! $this->truncates() || $maxWidth > 0) {
            return;
        }

        // The height ceiling as ownSize() set it; the width's is 0.
        $covers = $this->parent() instanceof TKOverlay && $this->fills() === [true, true];
        Rwidget_set_max_size($this->native, 0, $maxHeight > 0 ? $maxHeight : ($covers ? 0 : -1));
    }

    /**
     * @return array{Align, Align}
     */
    public function slotAligned(): array
    {
        [$horizontal, $vertical] = $this->ownSlot();

        return [$this->truncates() && $this->maximumSize()[0] <= 0 ? Align::FILL : $horizontal, $vertical];
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
        $this->font_spec = $font;
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
