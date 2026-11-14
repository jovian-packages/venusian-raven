<?php

namespace Jovian\Toolkits\Raven\Primitives\Concerns;

use Surface\NutsAndBolts\Color;

/** A Raven button paints its own fill over its border: a background colours the button's control roles. */
trait RavenButtonColors
{
    protected function applyBackground(?Color $color): void
    {
        foreach ([R_COLOR_CONTROL, R_COLOR_CONTROL_HOVERED, R_COLOR_CONTROL_PRESSED] as $i => $role) {
            if (is_null($color)) {
                Rwidget_clear_color($this->control, $role);

                continue;
            }
            // Hovered a little lighter, pressed a little darker, as Raven's theme does.
            $shift = [0.0, 0.08, -0.08][$i];
            Rwidget_set_color($this->control, $role, max(0.0, min(1.0, $color->red + $shift)), max(0.0, min(1.0, $color->green + $shift)), max(0.0, min(1.0, $color->blue + $shift)), $color->alpha);
        }
    }
}
