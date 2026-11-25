<?php

namespace Jovian\Toolkits\Raven\UI\Concerns;

use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Corners;

/**
 * A Raven button paints its own surface: its background is its control colours, its corner
 * radius its corner metric, and its border its border metric and control border colour.
 *
 * A frameless button (borderless or link) has no line. With no background it is Raven's flat
 * button (R_BUTTON_FLAT), whose hovered and pressed colours are kept at alpha 0, so it shows no
 * fill in any state. With a background it is Raven's default button with its line taken away
 * (R_METRIC_BORDER_WIDTH 0, R_COLOR_CONTROL_BORDER at alpha 0) and its control colours the
 * background: a flat button paints nothing at rest, so it could not show one.
 */
trait RavenButtonSurface
{
    /** The background set; null is the theme's. */
    private ?Color $surface = null;

    /** The border set: 0 is the theme's own button line. */
    private int $line_width = 0;

    private ?Color $line_color = null;

    /**
     * Whether the button has no line of its own (borderless, link).
     */
    protected function surfaceIsFlat(): bool
    {
        return false;
    }

    /**
     * The surface's colour at rest (R_COLOR_CONTROL); hovered 0.08 lighter and pressed 0.08
     * darker on each channel. Null gives the theme's three back.
     */
    protected function applyBackground(?Color $color): void
    {
        $this->surface = $color;
        $this->paintSurface();
    }

    /**
     * The style a frameless button takes, its control colours and its line, from the
     * background, the border and whether it is frameless. A button that is not frameless keeps
     * the style it has (a toggle button's primary while pressed).
     */
    protected function paintSurface(): void
    {
        $color = $this->surface;
        $frameless = $this->surfaceIsFlat();

        if ($frameless) {
            Rbutton_set_style($this->control, is_null($color) ? R_BUTTON_FLAT : R_BUTTON_DEFAULT);
        }

        foreach ([R_COLOR_CONTROL => 0.0, R_COLOR_CONTROL_HOVERED => 0.08, R_COLOR_CONTROL_PRESSED => -0.08] as $role => $shift) {
            if (is_null($color)) {
                $frameless && $role !== R_COLOR_CONTROL
                    ? Rwidget_set_color($this->control, $role, 0.0, 0.0, 0.0, 0.0)
                    : Rwidget_clear_color($this->control, $role);

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

        $this->paintLine();
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

    /**
     * A button rounds with one metric, R_METRIC_CORNER_RADIUS: corners that agree take it (0
     * the theme's), corners that differ are square (rounding all four would be wrong).
     */
    protected function applyCorners(Corners $corners): void
    {
        $corners->isUniform()
            ? $this->applyCornerRadius($corners->topLeft)
            : Rwidget_set_metric($this->control, R_METRIC_CORNER_RADIUS, 0.0);
    }

    /**
     * The button's own line: R_METRIC_BORDER_WIDTH and R_COLOR_CONTROL_BORDER, a null colour
     * the theme's. Width 0 gives the theme's button line back. A frameless button keeps none;
     * Raven's own look draws none round a primary button, and the platform looks draw theirs
     * at their own width.
     */
    protected function applyBorder(int $width, ?Color $color): void
    {
        $this->line_width = $width;
        $this->line_color = $color;
        $this->paintLine();
    }

    private function paintLine(): void
    {
        if ($this->surfaceIsFlat()) {
            Rwidget_set_metric($this->control, R_METRIC_BORDER_WIDTH, 0.0);
            Rwidget_set_color($this->control, R_COLOR_CONTROL_BORDER, 0.0, 0.0, 0.0, 0.0);

            return;
        }

        if ($this->line_width === 0) {
            Rwidget_clear_metric($this->control, R_METRIC_BORDER_WIDTH);
            Rwidget_clear_color($this->control, R_COLOR_CONTROL_BORDER);

            return;
        }

        $color = $this->line_color;
        Rwidget_set_metric($this->control, R_METRIC_BORDER_WIDTH, $this->line_width);
        is_null($color)
            ? Rwidget_clear_color($this->control, R_COLOR_CONTROL_BORDER)
            : Rwidget_set_color($this->control, R_COLOR_CONTROL_BORDER, $color->red, $color->green, $color->blue, $color->alpha);
    }
}
