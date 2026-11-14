<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenContainer;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenStack;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKColumn;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;

class RavenColumn extends TKColumn implements RavenContainer
{
    use RavenPrimitive;
    use RavenStack;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, int $spacing, int $padding)
    {
        parent::__construct($name, $window, $parent, $placement, $spacing, $padding);
        $this->buildStack(true);
    }
}
