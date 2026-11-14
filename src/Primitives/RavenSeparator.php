<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Surface\Toolkits\Primitives\TKSeparator;

class RavenSeparator extends TKSeparator implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, bool $horizontal)
    {
        parent::__construct($name, $window, $parent, $placement, $horizontal);
        $line = $this->adoptNative(Rwidget_create(R_WIDGET_SEPARATOR));
        Rseparator_set_orientation($line, $horizontal ? R_HORIZONTAL : R_VERTICAL);
    }
}
