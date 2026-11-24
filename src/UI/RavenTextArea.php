<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\TKTextArea;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;
use Jovian\Toolkits\Raven\UI\Concerns\RavenText;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * Raven's text editor in a border, without line numbers. It lays lines out from the left
 * only, colours text only through syntax languages, and scrolls to a line but reports no
 * offset: what asks for more is refused.
 */
class RavenTextArea extends TKTextArea
{
    use RavenText;
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rtext_editor_create($this->value()));
        Rtext_editor_set_line_numbers($this->control, R_FALSE);
        Rtext_editor_set_word_wrap($this->control, $this->wraps() ? R_TRUE : R_FALSE);

        $self = WeakReference::create($this);
        Rtext_editor_set_changed_callback($this->control, static function (int $editor) use ($self): void {
            $self->get()?->typed((string) Rtext_editor_get_text($editor));
        }, null);
    }

    protected function applyValue(string $value): void
    {
        Rtext_editor_set_text($this->control, $value);
    }

    protected function applyFont(FontSpec $font): void
    {
        Rtext_editor_set_font($this->control, self::ravenFont($font), $font->size);
    }

    protected function applyTextColor(?Color $color): void
    {
        is_null($color)
            ? Rwidget_clear_color($this->control, R_COLOR_TEXT)
            : Rwidget_set_color($this->control, R_COLOR_TEXT, $color->red, $color->green, $color->blue, $color->alpha);
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
            $this->refuse('lays lines out from the left only');
        }
    }

    protected function applyLineBackgrounds(array $backgrounds): void
    {
        if ($backgrounds !== []) {
            $this->refuse('has no line backgrounds');
        }
    }

    protected function applyRangeStyles(array $styles): void
    {
        if ($styles !== []) {
            $this->refuse('styles no ranges of text');
        }
    }

    protected function applySelectLines(int $first, int $last): void
    {
        $end = mb_strlen((string) Rtext_editor_get_line($this->control, $last));
        Rtext_editor_select($this->control, $first, 0, $last, $end);
    }

    protected function applyScrollTo(float $x, float $y): void
    {
        $this->refuse('scrolls to a line only (scrollToLine)');
    }

    protected function applyScrollToLine(int $line): void
    {
        Rtext_editor_scroll_to_line($this->control, $line);
    }

    protected function nativeSelectedLines(): array
    {
        if (Rtext_editor_get_selection($this->control, $from, $from_column, $to, $to_column) !== R_TRUE) {
            Rtext_editor_get_cursor($this->control, $line, $column);

            return [$line, $line];
        }

        return [min($from, $to), max($from, $to)];
    }

    protected function nativeScrollPosition(): ScrollPosition
    {
        $this->refuse('reports no scroll offset');
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rtext_editor_set_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }

    /**
     * @throws ToolkitException Always.
     */
    private function refuse(string $limit): never
    {
        throw new ToolkitException("Raven's text editor {$limit}, for '{$this->path()}'.");
    }
}
