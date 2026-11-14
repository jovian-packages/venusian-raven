<?php

namespace Jovian\Toolkits\Raven\Exceptions;

use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * A Topside feature Raven 0.2.3 has no way to provide. Thrown where the feature is asked
 * for, naming what is missing, rather than faking it.
 */
class RavenIncompatibilityException extends ToolkitRuntimeException
{
    public static function feature(string $feature, string $why): static
    {
        return new static("{$feature} is not available on raven: {$why}");
    }
}
