<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKCheckbox;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven check box inside a border. Its changed callback tells topside the new state; a
 * change made from code reaches topside as the state it already holds.
 */
class RavenCheckbox extends TKCheckbox
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    /**
     * The callback holds the primitive weakly: a strong capture would be a cycle through
     * ext-raven's held callback that PHP's collector cannot see.
     */
    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rcheckbox_create($this->label()));
        Rcheckbox_set_checked($this->control, $this->isChecked() ? R_TRUE : R_FALSE);

        $self = WeakReference::create($this);
        Rcheckbox_set_changed_callback($this->control, static function (int $box, int $checked) use ($self): void {
            $self->get()?->toggled($checked === R_TRUE);
        }, null);
    }

    /**
     * The callback goes first: ext-raven would otherwise hold it until it next sweeps.
     */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rcheckbox_set_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }

    protected function applyLabel(string $label): void
    {
        Rcheckbox_set_text($this->control, $label);
    }

    protected function applyChecked(bool $checked): void
    {
        Rcheckbox_set_checked($this->control, $checked ? R_TRUE : R_FALSE);
    }
}
