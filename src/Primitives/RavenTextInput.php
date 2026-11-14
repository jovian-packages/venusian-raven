<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenFonts;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Toolkits\Primitives\TKTextInput;

class RavenTextInput extends TKTextInput implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $value, ?string $placeholder, bool $secret)
    {
        parent::__construct($name, $window, $parent, $placement, $value, $placeholder, $secret);
        $input = $this->adoptNative(Rwidget_create(R_WIDGET_TEXT_INPUT));
        Rtext_input_set_text($input, $value);
        Rtext_input_set_placeholder($input, $placeholder ?? '');
        Rtext_input_set_password($input, $secret ? R_TRUE : R_FALSE);
        $self = WeakReference::create($this);
        Rtext_input_set_changed_callback($input, static function (int $input, ?string $text) use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->nativeValueChanged((string) $text);
                $primitive->session()->post(new TextChanged($primitive->window->name(), $primitive->path(), $primitive->uuid, (string) $text));
            }
        }, null);
        Rtext_input_set_committed_callback($input, static function () use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->session()->post(new TextSubmitted($primitive->window->name(), $primitive->path(), $primitive->uuid, $primitive->value));
            }
        }, null);
    }

    protected function applyValue(string $value): void
    {
        $this->applying(fn () => Rtext_input_set_text($this->control, $value));
    }

    protected function applyPlaceholder(?string $placeholder): void
    {
        Rtext_input_set_placeholder($this->control, $placeholder ?? '');
    }

    /** Raven's text input aligns its text left only: another alignment is refused. */
    protected function applyAlignment(TextAlignment $alignment): void
    {
        if ($alignment !== TextAlignment::LEFT) {
            throw \Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException::feature('A text input aligned '.$alignment->value, 'Raven\'s text input draws its text from the left only.');
        }
    }
}
