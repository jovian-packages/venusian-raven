<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenFonts;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Toolkits\Primitives\TKTextArea;
use WeakReference;

/**
 * Raven's text editor, line numbers off. Raven colours text only through its syntax languages
 * and reports no scroll offset: coloured line backgrounds and ranges, scroll positions and
 * alignments other than left are refused.
 */
class RavenTextArea extends TKTextArea implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $value)
    {
        parent::__construct($name, $window, $parent, $placement, $value);
        $editor = $this->adoptNative(Rtext_editor_create($value));
        Rtext_editor_set_line_numbers($editor, R_FALSE);
        $self = WeakReference::create($this);
        Rtext_editor_set_changed_callback($editor, static function (int $editor) use ($self): void {
            $primitive = $self->get();
            if (! $primitive?->handling()) {
                return;
            }
            $text = (string) Rtext_editor_get_text($editor);
            $primitive->nativeValueChanged($text);
            $primitive->session()->post(new TextChanged($primitive->window->name(), $primitive->path(), $primitive->uuid, $text));
        }, null);
    }

    protected function applyValue(string $value): void
    {
        $this->applying(fn () => Rtext_editor_set_text($this->control, $value));
    }

    protected function applyFont(FontSpec $font): void
    {
        Rtext_editor_set_font($this->control, RavenFonts::font($font), $font->size);
    }

    protected function applyReadOnly(bool $read_only): void
    {
        Rtext_editor_set_read_only($this->control, $read_only ? R_TRUE : R_FALSE);
    }

    protected function applyWrap(bool $wrap): void
    {
        Rtext_editor_set_word_wrap($this->control, $wrap ? R_TRUE : R_FALSE);
    }

    protected function applyTabWidth(int $columns): void
    {
        Rtext_editor_set_tab_size($this->control, $columns);
    }

    protected function applyAlignment(TextAlignment $alignment): void
    {
        if ($alignment !== TextAlignment::LEFT) {
            throw RavenIncompatibilityException::feature('A text area aligned '.$alignment->value, 'Raven\'s text editor lays its lines out from the left only.');
        }
    }

    protected function applyLineBackgrounds(array $backgrounds): void
    {
        if ($backgrounds !== []) {
            throw RavenIncompatibilityException::feature('Line backgrounds', 'Raven\'s text editor colours lines only through its syntax languages and diagnostic markers.');
        }
    }

    protected function applyRangeStyles(array $styles): void
    {
        if ($styles !== []) {
            throw RavenIncompatibilityException::feature('Text range styles', 'Raven\'s text editor colours text only through its syntax languages.');
        }
    }

    /** Lines $first … $last, whole. */
    protected function applySelectLines(int $first, int $last): void
    {
        $end = mb_strlen((string) Rtext_editor_get_line($this->control, $last));
        Rtext_editor_select($this->control, $first, 0, $last, $end);
    }

    protected function applyScrollTo(float $x, float $y): void
    {
        throw RavenIncompatibilityException::feature('Scrolling a text area to a position', 'Raven\'s text editor scrolls to a line (scrollToLine) and reports no scroll offset.');
    }

    protected function applyScrollToLine(int $line): void
    {
        Rtext_editor_scroll_to_line($this->control, $line);
    }

    protected function nativeSelectedLines(): array
    {
        if (Rtext_editor_get_selection($this->control, $from_line, $from_column, $to_line, $to_column) !== R_TRUE) {
            Rtext_editor_get_cursor($this->control, $line, $column);

            return [$line, $line];
        }

        return [min($from_line, $to_line), max($from_line, $to_line)];
    }

    protected function nativeScrollPosition(): ScrollPosition
    {
        throw RavenIncompatibilityException::feature('A text area\'s scroll position', 'Raven\'s text editor reports no scroll offset.');
    }
}
