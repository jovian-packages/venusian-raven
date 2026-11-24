<?php

namespace Jovian\Toolkits\Raven\UI;

use Surface\Toolkits\UI\TKRow;
use Jovian\Toolkits\Raven\UI\Concerns\RavenBoxChildren;

/**
 * A Raven horizontal box in a border.
 */
class RavenRow extends TKRow
{
    use RavenBoxChildren;

    protected function vertical(): bool
    {
        return false;
    }
}
