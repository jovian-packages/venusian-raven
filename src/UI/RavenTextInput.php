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
 */
class RavenTextInput extends TKTextInput
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }
    use RavenText;

    private const int NATURAL_WIDTH = 120;

    protected function bootstrapNative(): void
    {
        $input = Rwidget_create(R_WIDGET_TEXT_INPUT);
        Rwidget_set_min_size($input, self::NATURAL_WIDTH, 0);
        Rtext_input_set_text($input, $this->value());
        Rtext_input_set_placeholder($input, $this->placeholder() ?? '');
        Rtext_input_set_password($input, $this->isSecret() ? R_TRUE : R_FALSE);
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

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rtext_input_set_changed_callback($this->control, null, null);
            Rtext_input_set_committed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
