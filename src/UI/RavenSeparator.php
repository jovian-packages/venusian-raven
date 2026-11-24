<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKSeparator;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven separator inside a border.
 */
class RavenSeparator extends TKSeparator
{
    use RavenNative;

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_SEPARATOR));
        Rseparator_set_orientation($this->control, $this->isHorizontal() ? R_HORIZONTAL : R_VERTICAL);
    }
}
