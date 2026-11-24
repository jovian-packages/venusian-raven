<?php

namespace Jovian\Toolkits\Raven\UI\Concerns;

use Surface\NutsAndBolts\Color;

/**
 * A Raven button paints its own surface: its background is its control colours and its
 * corner radius its corner metric.
 */
trait RavenButtonSurface
{
    /**
     * The surface's colour at rest (R_COLOR_CONTROL); hovered 0.08 lighter and pressed 0.08
     * darker on each channel. Null gives the theme's three back.
     */
    protected function applyBackground(?Color $color): void
    {
        foreach ([R_COLOR_CONTROL => 0.0, R_COLOR_CONTROL_HOVERED => 0.08, R_COLOR_CONTROL_PRESSED => -0.08] as $role => $shift) {
            if (is_null($color)) {
                Rwidget_clear_color($this->control, $role);

                continue;
            }

            Rwidget_set_color(
                $this->control,
                $role,
                max(0.0, min(1.0, $color->red + $shift)),
                max(0.0, min(1.0, $color->green + $shift)),
                max(0.0, min(1.0, $color->blue + $shift)),
                $color->alpha,
            );
        }
    }

    /**
     * Rounds the button's surface. 0.0 gives the theme's corners back.
     */
    protected function applyCornerRadius(float $radius): void
    {
        $radius > 0.0
            ? Rwidget_set_metric($this->control, R_METRIC_CORNER_RADIUS, $radius)
            : Rwidget_clear_metric($this->control, R_METRIC_CORNER_RADIUS);
    }
}
