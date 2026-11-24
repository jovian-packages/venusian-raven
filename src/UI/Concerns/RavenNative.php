<?php

namespace Jovian\Toolkits\Raven\UI\Concerns;

use RuntimeException;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\TKTransition;

/**
 * What every Raven primitive does the same way with its widgets.
 *
 * Two Rids: the native, which the parent holds, and the control, which takes focus and
 * input (the label, the button, a container's box). A primitive that needs a background,
 * rounded corners or padding inside its background has a Raven border as its native with the
 * control inside it; a button paints its own surface, so its native is the control itself.
 *
 * A child's fill and alignment live on its own slot in the parent's box. Fill is the share of
 * the box's axis: a filling child shares the spare room (Rslot_set_fill 1), the others take
 * their desired size (Rslot_set_auto); fill across the box's axis asks for nothing, the slot
 * spans it already. Alignment places the widget inside its slot whatever fill says
 * (Rslot_set_halign / Rslot_set_valign): FILL stretches it over the slot, the others keep its
 * desired size there. Which axis is the box's own is the container's to say, so a column or
 * row places the slot in childLayoutChanged(); the content container places its own slot in
 * the window's vertical box.
 *
 * Transitions are Raven's own appear transitions (Rwidget_play_transition), which Raven runs:
 * nothing is stepped from PHP. FADE plays R_TRANSITION_FADE, SLIDE_LEFT and SLIDE_RIGHT
 * R_TRANSITION_SLIDE_LEFT / _RIGHT (28 points, no bounce), SLIDE_UP and SLIDE_DOWN
 * R_TRANSITION_FLY_UP / _DOWN (56 points, with a small scale and a bounce: Raven has no
 * vertical slide). Raven moves them with springs, so the seconds asked for have no Raven
 * equivalent and are not used. Raven has no disappear transition: hiding with one hides at
 * once.
 */
trait RavenNative
{
    /** What the parent holds: the border, or the control itself. 0 once removed. */
    protected int $native = 0;

    /** What takes focus and input. 0 once removed. */
    protected int $control = 0;

    /**
     * The widget the parent holds.
     *
     * @throws RuntimeException Once removed.
     */
    public function native(): int
    {
        return $this->native !== 0 ? $this->native : throw new RuntimeException("'{$this->path()}' is removed.");
    }

    /**
     * The widget that takes focus and input.
     *
     * @throws RuntimeException Once removed.
     */
    public function control(): int
    {
        return $this->control !== 0 ? $this->control : throw new RuntimeException("'{$this->path()}' is removed.");
    }

    /**
     * What a window focuses on present() when this primitive is its initial focus.
     */
    public function focusWidget(): int
    {
        return $this->control();
    }

    /**
     * Take $control as this primitive's control. Wrapped, it goes inside a border with no
     * padding and no background (a fresh border paints the theme's panel colour), and the
     * border is the native.
     */
    protected function adoptNative(int $control, bool $wrapped = true): void
    {
        $this->control = $control;

        if (! $wrapped) {
            $this->native = $control;

            return;
        }

        $this->native = Rwidget_create(R_WIDGET_BORDER);
        Rborder_set_padding($this->native, 0, 0, 0, 0);
        Rborder_set_background($this->native, 0, 0, 0, 0);
        Rwidget_add_child($this->native, $control);
    }

    /**
     * The parent took the native out before this. Rwidget_destroy deletes the native and
     * everything inside it.
     */
    protected function releaseNative(): void
    {
        if ($this->native !== 0 && Rwidget_is_valid($this->native) === R_TRUE) {
            Rwidget_destroy($this->native);
        }

        $this->native = 0;
        $this->control = 0;
    }

    /**
     * Showing with a transition plays it as the native appears. Hiding always collapses at
     * once (Raven has no disappear transition), and stops a transition still running, which
     * would otherwise go on from where it was at the next show.
     */
    protected function applyVisible(bool $visible, ?TKTransition $transition, float $seconds): void
    {
        if (! $visible) {
            Rwidget_play_transition($this->native, R_TRANSITION_NONE, 0.0);
            Rwidget_set_visibility($this->native, R_COLLAPSED);

            return;
        }

        Rwidget_set_visibility($this->native, R_VISIBLE);
        Rwidget_play_transition($this->native, is_null($transition) || $seconds <= 0.0 ? R_TRANSITION_NONE : self::ravenTransition($transition), 0.0);
    }

    /**
     * The native's own flag. Raven keeps what is inside a disabled widget from input and draws
     * it dimmed itself.
     */
    protected function applyEnabled(bool $enabled): void
    {
        Rwidget_set_enabled($this->native, $enabled ? R_TRUE : R_FALSE);
    }

    protected function applyOpacity(float $opacity): void
    {
        Rwidget_set_opacity($this->native, $opacity);
    }

    /**
     * Raven focuses only a focusable, enabled, shown widget: whether it took the focus is read back.
     */
    protected function nativeFocus(): bool
    {
        Rwidget_focus($this->control);

        return Rwidget_is_focused($this->control) === R_TRUE;
    }

    /**
     * Whether the control is its window's focus widget.
     */
    public function hasFocus(): bool
    {
        return $this->control !== 0
            && Rwidget_is_valid($this->control) === R_TRUE
            && Rwidget_is_focused($this->control) === R_TRUE;
    }

    /**
     * The native's bounds after the last layout (Raven lays out first when it is due). A
     * collapsed native keeps the bounds it had when it was last laid out.
     *
     * @return array{int, int}
     */
    public function size(): array
    {
        if ($this->native === 0 || Rwidget_is_valid($this->native) !== R_TRUE) {
            return [0, 0];
        }
        Rwidget_get_rect($this->native, $x, $y, $width, $height);

        return [(int) round($width), (int) round($height)];
    }

    /**
     * The border's colour; null is no colour (alpha 0 draws no background).
     */
    protected function applyBackground(?Color $color): void
    {
        is_null($color)
            ? Rborder_set_background($this->native, 0, 0, 0, 0)
            : Rborder_set_background($this->native, $color->red, $color->green, $color->blue, $color->alpha);
    }

    /**
     * Rounds the border's background; what is inside is not clipped.
     */
    protected function applyCornerRadius(float $radius): void
    {
        Rborder_set_corner_radius($this->native, $radius, $radius, $radius, $radius);
    }

    /**
     * A column or row placed the slot already (childLayoutChanged); a fixed places its
     * children by frame. The content container takes its share of the window's vertical box:
     * all of its height when it fills vertically (mountContent() gives it that to start).
     */
    protected function applyFill(bool $horizontal, bool $vertical): void
    {
        if (is_null($this->parent())) {
            self::placeShare($this->native, true, $this->fills());
        }
    }

    /**
     * As applyFill(): the content container's place inside its slot in the window's box.
     */
    protected function applyAlign(Align $horizontal, Align $vertical): void
    {
        if (is_null($this->parent())) {
            self::placeAlignment($this->native, $this->aligned());
        }
    }

    /**
     * A floor on the size the native asks of its parent. Inside a fixed the frame sizes the
     * native too, and the larger of the two wins: the fixed sees to that in its
     * childLayoutChanged(), which runs first.
     */
    protected function applyMinSize(int $width, int $height): void
    {
        Rwidget_set_min_size($this->native, $width, $height);
    }

    /**
     * $native's share of a box's axis: a filling child shares the spare room (fill 1), the
     * others take their desired size. Fill across the axis changes nothing.
     *
     * @param array{bool, bool} $fills horizontal, vertical
     */
    protected static function placeShare(int $native, bool $vertical, array $fills): void
    {
        ($vertical ? $fills[1] : $fills[0]) ? Rslot_set_fill($native, 1.0) : Rslot_set_auto($native);
    }

    /**
     * Where $native sits inside its slot, on each axis, whatever its fill.
     *
     * @param array{Align, Align} $aligned horizontal, vertical
     */
    protected static function placeAlignment(int $native, array $aligned): void
    {
        [$alignH, $alignV] = $aligned;

        Rslot_set_halign($native, match ($alignH) {
            Align::START => R_HALIGN_LEFT,
            Align::CENTER => R_HALIGN_CENTER,
            Align::END => R_HALIGN_RIGHT,
            Align::FILL => R_HALIGN_FILL,
        });
        Rslot_set_valign($native, match ($alignV) {
            Align::START => R_VALIGN_TOP,
            Align::CENTER => R_VALIGN_CENTER,
            Align::END => R_VALIGN_BOTTOM,
            Align::FILL => R_VALIGN_FILL,
        });
    }

    private static function ravenTransition(TKTransition $transition): int
    {
        return match ($transition) {
            TKTransition::FADE => R_TRANSITION_FADE,
            TKTransition::SLIDE_LEFT => R_TRANSITION_SLIDE_LEFT,
            TKTransition::SLIDE_RIGHT => R_TRANSITION_SLIDE_RIGHT,
            TKTransition::SLIDE_UP => R_TRANSITION_FLY_UP,
            TKTransition::SLIDE_DOWN => R_TRANSITION_FLY_DOWN,
        };
    }
}
