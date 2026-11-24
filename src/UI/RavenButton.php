<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKButton;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;
use Jovian\Toolkits\Raven\UI\Concerns\RavenButtonSurface;

/**
 * A Raven button. It paints its own surface, so it is its own native with no border around
 * it: its background is the button's control colours and its corner radius the button's
 * corner metric. It takes keyboard focus as Raven makes every button do; its clicked callback
 * posts ButtonClicked (Raven calls it for a click, Space or Enter, never while the button or a
 * container above it is disabled).
 */
class RavenButton extends TKButton
{
    use RavenButtonSurface, RavenNative {
        RavenButtonSurface::applyBackground insteadof RavenNative;
        RavenButtonSurface::applyCornerRadius insteadof RavenNative;
        releaseNative as private releaseWidget;
    }

    /**
     * The callback holds the primitive weakly: a strong capture would be a cycle through
     * ext-raven's held callback that PHP's collector cannot see.
     */
    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rbutton_create($this->label()), wrapped: false);

        $self = WeakReference::create($this);
        Rbutton_set_clicked_callback($this->control, static function (int $button, mixed $data) use ($self): void {
            $self->get()?->clicked();
        }, null);
    }

    /**
     * The callback goes first: ext-raven would otherwise hold it until it next sweeps.
     */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rbutton_set_clicked_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }

    protected function applyLabel(string $label): void
    {
        Rbutton_set_text($this->control, $label);
    }
}
