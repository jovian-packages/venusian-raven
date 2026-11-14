<?php

namespace Jovian\Toolkits\Raven\Services;

use Surface\Contracts\Toolkits\Services\SystemAccent;
use Surface\NutsAndBolts\Color;

/** The accent of Raven's theme, which follows the system's where the platform reports one. */
final class RavenSystemAccent implements SystemAccent
{
    public function color(): ?Color
    {
        Rtheme_get_color(R_COLOR_ACCENT, $r, $g, $b, $a);

        return new Color($r, $g, $b, $a);
    }
}
