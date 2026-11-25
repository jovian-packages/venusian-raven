<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKToggleButton;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;
use Jovian\Toolkits\Raven\UI\Concerns\RavenButtonSurface;

/**
 * A Raven button, drawn primary while pressed. Raven buttons keep no state: each click
 * turns topside's over.
 */
class RavenToggleButton extends TKToggleButton
{
    use RavenButtonSurface, RavenNative {
        RavenButtonSurface::applyBackground insteadof RavenNative;
        RavenButtonSurface::applyCornerRadius insteadof RavenNative;
        RavenButtonSurface::applyBorder insteadof RavenNative;
        RavenButtonSurface::applyCorners insteadof RavenNative;
        releaseNative as private releaseWidget;
    }

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rbutton_create($this->label()), wrapped: false);
        $this->applyPressed($this->isPressed());

        $self = WeakReference::create($this);
        Rbutton_set_clicked_callback($this->control, static function () use ($self): void {
            $button = $self->get();
            if (is_null($button) || $button->isRemoved()) {
                return;
            }
            $button->toggled(! $button->isPressed());
            $button->applyPressed($button->isPressed());
        }, null);
    }

    protected function applyLabel(string $label): void
    {
        Rbutton_set_text($this->control, $label);
    }

    protected function applyPressed(bool $pressed): void
    {
        Rbutton_set_style($this->control, $pressed ? R_BUTTON_PRIMARY : R_BUTTON_DEFAULT);
    }

    /** The callback goes first: ext-raven would otherwise hold it until it next sweeps. */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rbutton_set_clicked_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }
}
