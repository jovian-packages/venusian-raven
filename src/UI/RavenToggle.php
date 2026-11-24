<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKToggle;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven check box drawn as a switch (R_CHECKBOX_SWITCH), with no text, inside a border.
 * Its changed callback tells topside the new state; a change made from code reaches topside
 * as the state it already holds.
 */
class RavenToggle extends TKToggle
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /**
     * The callback holds the primitive weakly, as RavenCheckbox's does.
     */
    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rcheckbox_create(''));
        Rcheckbox_set_style($this->control, R_CHECKBOX_SWITCH);
        Rcheckbox_set_checked($this->control, $this->isOn() ? R_TRUE : R_FALSE);

        $self = WeakReference::create($this);
        Rcheckbox_set_changed_callback($this->control, static function (int $switch, int $checked) use ($self): void {
            $self->get()?->toggled($checked === R_TRUE);
        }, null);
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rcheckbox_set_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }

    protected function applyOn(bool $on): void
    {
        Rcheckbox_set_checked($this->control, $on ? R_TRUE : R_FALSE);
    }
}
