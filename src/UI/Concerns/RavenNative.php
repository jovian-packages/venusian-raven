<?php

namespace Jovian\Toolkits\Raven\UI\Concerns;

use REvent;
use RuntimeException;
use WeakReference;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\UI\TKFixed;
use Surface\Toolkits\UI\TKOverlay;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\Cursor;
use Surface\Contracts\Toolkits\UI\Corners;
use Surface\Contracts\Toolkits\UI\Insets;
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
 * once. The MOVE transitions (in from an edge by the view's own size, no fade) are not
 * shown: Raven has no public way to move a widget, no transition distance and no closing
 * transition, and its slide is 28 points with a fade, which is not this transition. A view
 * given one shows and hides at once.
 *
 * A clickable group hears primary clicks through an event callback on its native
 * (Rwidget_set_event_callback). Raven sends a press and a release to the hit-testable widget
 * under the pointer and bubbles them up through its parents, each seeing the point relative
 * to itself; a label, picture or box lets the mouse through to the border round it, which
 * passes it on, so what is passive inside the group reaches the group's border, and a
 * button, field or other control keeps its own. A press there is the group's (consumed, so
 * a clickable group above it does not take it as well) and the release that follows on it
 * clicks. The window's input watcher forgets the press at the next one anywhere, so a press
 * elsewhere and a release here click nothing.
 *
 * Margin is the native's slot padding (Rslot_set_padding): the space its parent keeps round
 * it, outside its background. Raven's boxes, grid, scroll box, tab view, splitter and the
 * window's box read it; a canvas (the fixed) places by frame and reads none, so a margin there
 * is not shown. A border is the outline of the native's border (Rborder_set_outline), drawn
 * on its edge. The pointer and the tooltip are the native's: Raven looks for them from the
 * widget under the pointer up through its parents, so what is inside a container that has
 * none of its own shows the container's.
 */
trait RavenNative
{
    /** What the parent holds: the border, or the control itself. 0 once removed. */
    protected int $native = 0;

    /** What takes focus and input. 0 once removed. */
    protected int $control = 0;

    /** The floor applyMinSize() was given: the minimum size, or what a fixed covers. */
    private array $native_floor = [0, 0];

    /** Whether the native's event callback and the window's watcher hear clicks. */
    private bool $hearing_clicks = false;

    /** A clickable group's press on its native, waiting for the release. */
    private bool $group_pressed = false;

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
        $this->stopHearingClicks();
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
        Rwidget_play_transition($this->native, is_null($transition) || $seconds <= 0.0 ? R_TRANSITION_NONE : (self::ravenTransition($transition) ?? R_TRANSITION_NONE), 0.0);
    }

    /**
     * A group's clicks: its native's event callback, and the window's watcher that forgets a
     * press at the next one. Only groups call it (TKPrimitiveGroup::setClickable()).
     */
    protected function applyClickable(bool $on): void
    {
        if (! $on) {
            $this->stopHearingClicks();

            return;
        }

        $this->hearing_clicks = true;
        $self = WeakReference::create($this);
        Rwidget_set_event_callback($this->native, static function (int $widget, REvent $event) use ($self): int {
            $group = $self->get();

            return ! is_null($group) && ! $group->isRemoved() && $group->heardPress($event) ? R_TRUE : R_FALSE;
        }, null);
        $this->window()->watchInput("click:{$this->uuid()}", static function (REvent $event) use ($self): bool {
            if ($event->type === R_EVENT_MOUSE_DOWN && ! is_null($group = $self->get())) {
                $group->group_pressed = false;
            }

            return false;
        });
    }

    /**
     * A primary press on the native or on what is passive inside it is the group's; the
     * release on it clicks, at the point from the group's top-left.
     */
    private function heardPress(REvent $event): bool
    {
        if ($event->button !== R_MOUSE_LEFT) {
            return false;
        }

        if ($event->type === R_EVENT_MOUSE_DOWN) {
            $this->group_pressed = true;

            return true;
        }

        if ($event->type === R_EVENT_MOUSE_UP && $this->group_pressed) {
            $this->group_pressed = false;
            $this->groupClicked((float) $event->local_x, (float) $event->local_y);

            return true;
        }

        return false;
    }

    /**
     * The callback goes first: ext-raven would otherwise hold it until it next sweeps.
     */
    private function stopHearingClicks(): void
    {
        $this->group_pressed = false;
        if (! $this->hearing_clicks) {
            return;
        }

        $this->hearing_clicks = false;
        if ($this->native !== 0 && Rwidget_is_valid($this->native) === R_TRUE) {
            Rwidget_set_event_callback($this->native, null, null);
        }
        $this->window()->unwatchInput("click:{$this->uuid()}");
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
     * The border's colour; null is no colour (alpha 0 draws no background). A glass theme
     * makes every border with a colour a panel of frosted glass, which shows what is behind
     * it blurred: a colour set here is solid (Rwidget_set_material), painted as given, alpha
     * included. Null gives the border the theme's material again.
     */
    protected function applyBackground(?Color $color): void
    {
        if (is_null($color)) {
            Rborder_set_background($this->native, 0, 0, 0, 0);
            Rwidget_set_material($this->native, R_MATERIAL_DEFAULT);

            return;
        }

        Rborder_set_background($this->native, $color->red, $color->green, $color->blue, $color->alpha);
        Rwidget_set_material($this->native, R_MATERIAL_SOLID);
    }

    /**
     * Rounds the border's background; what is inside is not clipped.
     */
    protected function applyCornerRadius(float $radius): void
    {
        Rborder_set_corner_radius($this->native, $radius, $radius, $radius, $radius);
    }

    /**
     * Each corner its own radius on the border: Rborder_set_corner_radius takes all four.
     */
    protected function applyCorners(Corners $corners): void
    {
        Rborder_set_corner_radius($this->native, $corners->topLeft, $corners->topRight, $corners->bottomRight, $corners->bottomLeft);
    }

    /**
     * Raven clips a widget's descendants to its bounds, a rectangle (Rwidget_set_clipping): a
     * corner radius does not round the clip, which Raven has no way to do.
     */
    protected function applyClip(bool $clip): void
    {
        Rwidget_set_clipping($this->native, $clip ? R_TRUE : R_FALSE);
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
        $this->sizeNative();
    }

    /**
     * As applyFill(): the content container's place inside its slot in the window's box.
     */
    protected function applyAlign(Align $horizontal, Align $vertical): void
    {
        if (is_null($this->parent())) {
            self::placeAlignment($this->native, $this->slotAligned());
        }
    }

    /**
     * A floor on the size the native asks of its parent. Inside a fixed the frame sizes the
     * native too, and the larger of the two wins: the fixed sees to that in its
     * childLayoutChanged(), which runs first.
     */
    protected function applyMinSize(int $width, int $height): void
    {
        $this->native_floor = [$width, $height];
        $this->sizeNative();
    }

    /**
     * A ceiling on the size the native asks of its parent (Rwidget_set_max_size; 0 is none, -1
     * to Raven). The parent placed the slot already (childLayoutChanged); the content
     * container places its own.
     */
    protected function applyMaxSize(int $width, int $height): void
    {
        $this->sizeNative();
        if (is_null($this->parent())) {
            self::placeAlignment($this->native, $this->slotAligned());
        }
    }

    /**
     * The native's minimum and maximum size from minSize(), maxSize(), fill() and the parent.
     *
     * Raven stretches a FILL slot over its room whatever the widget's maximum, and places any
     * other slot at the widget's desired size, never past its room. So a view with a ceiling
     * is never in a FILL slot (slotAligned() makes FILL the start), and one that fills as well
     * asks for its whole ceiling (the floor rises to it): placed by its alignment, it is as big
     * as its room up to the ceiling. In a fixed the frame sizes it, so the floor stays. A child
     * that covers an overlay (fills both ways) asks for nothing: its desired size is capped at
     * 0, and its FILL slot still stretches it over the overlay.
     *
     * @internal For this adapter's containers.
     */
    public function sizeNative(): void
    {
        if ($this->native === 0) {
            return;
        }

        [$minWidth, $minHeight] = $this->native_floor;
        [$maxWidth, $maxHeight] = $this->maximumSize();
        [$fillsH, $fillsV] = $this->fills();
        $parent = $this->parent();

        if (! $parent instanceof TKFixed) {
            $minWidth = $fillsH && $maxWidth > 0 ? max($minWidth, $maxWidth) : $minWidth;
            $minHeight = $fillsV && $maxHeight > 0 ? max($minHeight, $maxHeight) : $minHeight;
        }
        Rwidget_set_min_size($this->native, $minWidth, $minHeight);

        // (a ceiling that way wins: the child is then placed by its alignment, at its ceiling)
        $covers = $parent instanceof TKOverlay && $fillsH && $fillsV;
        Rwidget_set_max_size(
            $this->native,
            $maxWidth > 0 ? $maxWidth : ($covers ? 0 : -1),
            $maxHeight > 0 ? $maxHeight : ($covers ? 0 : -1),
        );
    }

    /**
     * Where the parent places the native in its slot: align(), but the start for FILL on an
     * axis with a ceiling (a FILL slot would stretch it past it). In an overlay, filling an
     * axis without a ceiling is FILL there.
     *
     * @internal For this adapter's containers.
     * @return array{Align, Align}
     */
    public function slotAligned(): array
    {
        [$alignH, $alignV] = $this->aligned();
        [$fillsH, $fillsV] = $this->fills();
        [$maxWidth, $maxHeight] = $this->maximumSize();
        $overlay = $this->parent() instanceof TKOverlay;

        $place = static fn (Align $align, bool $fills, int $max): Align => match (true) {
            $max > 0 => $align === Align::FILL ? Align::START : $align,
            $overlay && $fills => Align::FILL,
            default => $align,
        };

        return [$place($alignH, $fillsH, $maxWidth), $place($alignV, $fillsV, $maxHeight)];
    }

    /**
     * The native's slot padding, which the parent keeps round it. A canvas reads none.
     */
    protected function applyMargin(Insets $margin): void
    {
        if ($this->parent() instanceof TKFixed) {
            return;
        }

        Rslot_set_padding($this->native, $margin->left, $margin->top, $margin->right, $margin->bottom);
    }

    /**
     * On the native, and on a control that sets a pointer of its own (a field's text beam),
     * which Raven would otherwise find first. Null gives each its own back. A shape Raven has
     * no cursor for (GRAB, GRABBING) is not shown: Raven's own pointer stays.
     */
    protected function applyCursor(?Cursor $cursor): void
    {
        $shape = is_null($cursor) ? null : self::ravenCursor($cursor);
        $own = $this->ownCursor();

        Rwidget_set_cursor($this->native, $shape ?? 0);
        if ($this->control !== $this->native && ! is_null($own)) {
            Rwidget_set_cursor($this->control, $shape ?? $own);
        }
    }

    /**
     * The pointer Raven gives the control by itself, null when it gives none.
     */
    protected function ownCursor(): ?int
    {
        return null;
    }

    /**
     * Raven shows the tooltip of the nearest widget under the pointer that has one.
     */
    protected function applyTooltip(?string $tooltip): void
    {
        Rwidget_set_tooltip($this->native, $tooltip);
    }

    /**
     * The native border's outline, on its edge. A null colour is the theme's R_COLOR_BORDER as
     * Raven resolves it for the border.
     */
    protected function applyBorder(int $width, ?Color $color): void
    {
        if (is_null($color)) {
            Rwidget_get_color($this->native, R_COLOR_BORDER, $red, $green, $blue, $alpha);
            Rborder_set_outline($this->native, $width, (float) $red, (float) $green, (float) $blue, (float) $alpha);

            return;
        }

        Rborder_set_outline($this->native, $width, $color->red, $color->green, $color->blue, $color->alpha);
    }

    /**
     * Raven's cursor for a shape; null where Raven has none.
     */
    protected static function ravenCursor(Cursor $cursor): ?int
    {
        return match ($cursor) {
            Cursor::ARROW => R_CURSOR_ARROW,
            Cursor::POINTER => R_CURSOR_HAND,
            Cursor::TEXT => R_CURSOR_IBEAM,
            Cursor::CROSSHAIR => R_CURSOR_CROSSHAIR,
            Cursor::MOVE => R_CURSOR_MOVE,
            Cursor::NOT_ALLOWED => R_CURSOR_NOT_ALLOWED,
            Cursor::RESIZE_HORIZONTAL => R_CURSOR_RESIZE_H,
            Cursor::RESIZE_VERTICAL => R_CURSOR_RESIZE_V,
            Cursor::RESIZE_NWSE => R_CURSOR_RESIZE_NWSE,
            Cursor::RESIZE_NESW => R_CURSOR_RESIZE_NESW,
            Cursor::GRAB, Cursor::GRABBING => null,
        };
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

    /**
     * Raven's appear transition for one; null for a move, which Raven cannot show.
     */
    private static function ravenTransition(TKTransition $transition): ?int
    {
        return match ($transition) {
            TKTransition::FADE => R_TRANSITION_FADE,
            TKTransition::SLIDE_LEFT => R_TRANSITION_SLIDE_LEFT,
            TKTransition::SLIDE_RIGHT => R_TRANSITION_SLIDE_RIGHT,
            TKTransition::SLIDE_UP => R_TRANSITION_FLY_UP,
            TKTransition::SLIDE_DOWN => R_TRANSITION_FLY_DOWN,
            TKTransition::MOVE_RIGHT, TKTransition::MOVE_LEFT, TKTransition::MOVE_TOP, TKTransition::MOVE_BOTTOM => null,
        };
    }
}
