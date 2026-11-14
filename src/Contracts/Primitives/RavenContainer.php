<?php

namespace Jovian\Toolkits\Raven\Contracts\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitive;

/** A raven container: re-applies a child's slot after its fill() or align() changed. */
interface RavenContainer extends RavenNative
{
    public function placeChild(TKPrimitive&RavenNative $child): void;
}
