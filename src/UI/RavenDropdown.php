<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKDropdown;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven combo box inside a border. Its changed callback tells topside the chosen index.
 * Raven may report a change while the items are replaced; that comes while $rebuilding and
 * is not the user's, so it is not passed on.
 */
class RavenDropdown extends TKDropdown
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    private bool $rebuilding = false;

    /**
     * The callback holds the primitive weakly, as RavenCheckbox's does.
     */
    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_COMBO_BOX));
        $this->applyOptions($this->options(), $this->selected());

        $self = WeakReference::create($this);
        Rcombo_box_set_changed_callback($this->control, static function (int $combo, int $index) use ($self): void {
            $dropdown = $self->get();
            if (! is_null($dropdown) && ! $dropdown->rebuilding) {
                $dropdown->chosen($index);
            }
        }, null);
    }

    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rcombo_box_set_changed_callback($this->control, null, null);
        }

        $this->releaseWidget();
    }

    protected function applyOptions(array $options, int $selected): void
    {
        $this->rebuilding = true;
        try {
            Rcombo_box_clear_items($this->control);
            foreach ($options as $option) {
                Rcombo_box_add_item($this->control, $option);
            }
            if ($selected >= 0) {
                Rcombo_box_set_selected($this->control, $selected);
            }
        } finally {
            $this->rebuilding = false;
        }
    }

    protected function applySelected(int $index): void
    {
        Rcombo_box_set_selected($this->control, $index);
    }
}
