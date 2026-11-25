<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\TKTextInput;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Jovian\Toolkits\Raven\UI\Concerns\RavenText;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven text input in a border. A limit of Raven's: its text starts at the left only
 * (another alignment is refused). Its text size is its own R_METRIC_FONT_SIZE.
 *
 * A search field is Raven's R_TEXT_INPUT_TYPE_SEARCH, which picks the on-screen keyboard and
 * draws the field as any other: Raven has no search field look. Borderless is the field's own
 * colours and line made clear: R_COLOR_INPUT, R_COLOR_BORDER (Raven's look) and
 * R_COLOR_CONTROL_BORDER (the platform looks) at alpha 0, R_METRIC_BORDER_WIDTH 0. A
 * background is the border's; the field's own fill is then clear, so it shows. Read-only and
 * the maximum length are Raven's own (Rtext_input_set_read_only, Rtext_input_set_max_length).
 */
class RavenTextInput extends TKTextInput
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }
    use RavenText;

    private const int NATURAL_WIDTH = 120;

    /** Whether a background is set: the border paints it, the field's fill is clear. */
    private bool $backed = false;

    protected function bootstrapNative(): void
    {
        $input = Rwidget_create(R_WIDGET_TEXT_INPUT);
        Rwidget_set_min_size($input, self::NATURAL_WIDTH, 0);
        Rtext_input_set_text($input, $this->value());
        Rtext_input_set_placeholder($input, $this->placeholder() ?? '');
        Rtext_input_set_password($input, $this->isSecret() ? R_TRUE : R_FALSE);
        if ($this->isSearch()) {
            Rtext_input_set_input_type($input, R_TEXT_INPUT_TYPE_SEARCH);
        }
        $this->adoptNative($input);

        $self = WeakReference::create($this);
        Rtext_input_set_changed_callback($input, static function (int $input, ?string $text) use ($self): void {
            $self->get()?->typed((string) $text);
        }, null);
        Rtext_input_set_committed_callback($input, static function () use ($self): void {
            $field = $self->get();
            if (! is_null($field) && ! $field->isRemoved()) {
                $field->submitted((string) Rtext_input_get_text($field->control));
            }
        }, null);
    }

    protected function applyValue(string $value): void
    {
        Rtext_input_set_text($this->control, $value);
    }

    protected function applyPlaceholder(?string $placeholder): void
    {
        Rtext_input_set_placeholder($this->control, $placeholder ?? '');
    }

    protected function applyFont(FontSpec $font): void
    {
        Rwidget_set_font($this->control, self::ravenFont($font));
        Rwidget_set_metric($this->control, R_METRIC_FONT_SIZE, $font->size);
    }

    protected function applyTextColor(?Color $color): void
    {
        is_null($color)
            ? Rwidget_clear_color($this->control, R_COLOR_TEXT)
            : Rwidget_set_color($this->control, R_COLOR_TEXT, $color->red, $color->green, $color->blue, $color->alpha);
    }

    /**
     * @throws ToolkitException For any alignment but LEFT.
     */
    protected function applyAlignment(TextAlignment $alignment): void
    {
        if ($alignment !== TextAlignment::LEFT) {
            throw new ToolkitException("Raven's text input draws from the left only: '{$this->path()}' cannot be aligned {$alignment->value}.");
        }
    }

    /**
     * The border paints it; the field's own fill is clear while one is set, so it shows.
     */
    protected function applyBackground(?Color $color): void
    {
        is_null($color)
            ? Rborder_set_background($this->native, 0, 0, 0, 0)
            : Rborder_set_background($this->native, $color->red, $color->green, $color->blue, $color->alpha);
        $this->backed = ! is_null($color);
        $this->paintField();
    }

    protected function applyBorderless(bool $borderless): void
    {
        foreach ([R_COLOR_BORDER, R_COLOR_CONTROL_BORDER] as $role) {
            $borderless
                ? Rwidget_set_color($this->control, $role, 0.0, 0.0, 0.0, 0.0)
                : Rwidget_clear_color($this->control, $role);
        }
        $borderless
            ? Rwidget_set_metric($this->control, R_METRIC_BORDER_WIDTH, 0.0)
            : Rwidget_clear_metric($this->control, R_METRIC_BORDER_WIDTH);
        $this->paintField();
    }

    protected function applyReadOnly(bool $readOnly): void
    {
        Rtext_input_set_read_only($this->control, $readOnly ? R_TRUE : R_FALSE);
    }

    /**
     * Raven's 0 is no limit.
     */
    protected function applyMaxLength(?int $length): void
    {
        Rtext_input_set_max_length($this->control, $length ?? 0);
    }

    /**
     * The field's text beam, which Raven gives it.
     */
    protected function ownCursor(): ?int
    {
        return R_CURSOR_IBEAM;
    }

    /**
     * The field's fill: clear when borderless or over a background, the theme's otherwise.
     */
    private function paintField(): void
    {
        $this->isBorderless() || $this->backed
            ? Rwidget_set_color($this->control, R_COLOR_INPUT, 0.0, 0.0, 0.0, 0.0)
            : Rwidget_clear_color($this->control, R_COLOR_INPUT);
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rtext_input_set_changed_callback($this->control, null, null);
            Rtext_input_set_committed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
