<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKColumn;
use Jovian\Toolkits\Raven\UI\Concerns\RavenBoxChildren;

/**
 * A Raven vertical box in a border.
 */
class RavenColumn extends TKColumn
{
    use RavenBoxChildren;

    protected function vertical(): bool
    {
        return true;
    }
}
