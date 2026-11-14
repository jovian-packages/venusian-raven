<?php

namespace Jovian\Toolkits\Raven\Primitives\Concerns;

use Closure;
use Jovian\Toolkits\Raven\Contracts\Primitives\RavenContainer;
use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\RavenSession;
use Jovian\Toolkits\Raven\RavenWindow;
use Jovian\Toolkits\Raven\Windows\RavenContextMenu;
use Surface\Contracts\Toolkits\Mail\Views\ViewResized;
use Surface\Contracts\Toolkits\Menus\ContextMenu as ContextMenuContract;
use Surface\Contracts\Toolkits\Primitives\Align;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Contracts\Toolkits\Primitives\TKTransition;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Menus\ContextMenu;
use WeakReference;

/**
 * The hooks every raven primitive shares. Each primitive is a Raven border (background, corner
 * radius, padding) around its control; the border is what its parent holds. Fill and align are
 * the parent's slot: fill along the parent's axis is a fill coefficient, fill across it and
 * align are the slot's alignment.
 */
trait RavenPrimitive
{
    /** The border the parent holds. */
    protected int $native = 0;

    /** The control inside it: the widget itself for leaves, the box for containers. */
    protected int $control = 0;

    /** True while a code-driven setter writes to the native, so its echo callback posts no mail. */
    protected bool $applying = false;

    /** Bumped by every transition: a timer of an older one does nothing. */
    protected int $transition_generation = 0;

    protected ?RavenContextMenu $popup = null;

    public function native(): int
    {
        return $this->native;
    }

    public function control(): int
    {
        return $this->control;
    }

    public function focusRid(): int
    {
        return $this->control;
    }

    public function fills(): array
    {
        return [$this->fill_horizontal, $this->fill_vertical];
    }

    public function ravenAlign(): array
    {
        return [
            match ($this->align_horizontal) {
                Align::START => R_HALIGN_LEFT,
                Align::CENTER => R_HALIGN_CENTER,
                Align::END => R_HALIGN_RIGHT,
                Align::FILL => R_HALIGN_FILL,
            },
            match ($this->align_vertical) {
                Align::START => R_VALIGN_TOP,
                Align::CENTER => R_VALIGN_CENTER,
                Align::END => R_VALIGN_BOTTOM,
                Align::FILL => R_VALIGN_FILL,
            },
        ];
    }

    /**
     * Take the concrete's control and wrap it in the border the parent holds.
     */
    protected function adoptNative(int $control): int
    {
        $this->control = $control;
        $this->native = Rwidget_create(R_WIDGET_BORDER);
        Rborder_set_padding($this->native, 0, 0, 0, 0);
        Rborder_set_background($this->native, 0, 0, 0, 0);
        Rwidget_add_child($this->native, $control);
        $this->ravenWindow()->adopt($this->native, $this);

        return $control;
    }

    protected function applyVisible(bool $visible): void
    {
        Rwidget_set_visibility($this->native, $visible ? R_VISIBLE : R_COLLAPSED);
    }

    protected function applyEnabled(bool $enabled): void
    {
        Rwidget_set_enabled($this->native, $enabled ? R_TRUE : R_FALSE);
    }

    protected function applyBackground(?Color $color): void
    {
        is_null($color)
            ? Rborder_set_background($this->native, 0, 0, 0, 0)
            : Rborder_set_background($this->native, $color->red, $color->green, $color->blue, $color->alpha);
    }

    protected function applyCornerRadius(float $radius): void
    {
        Rborder_set_corner_radius($this->native, $radius, $radius, $radius, $radius);
    }

    protected function applyOpacity(float $opacity): void
    {
        Rwidget_set_opacity($this->native, $opacity);
    }

    /**
     * Entering plays Raven's matching appear transition; Raven has none for leaving, so the
     * primitive fades to transparent over the time. $done runs after $seconds either way.
     */
    protected function applyTransition(TKTransition $transition, bool $entering, float $seconds, ?Closure $done): void
    {
        $generation = ++$this->transition_generation;
        if ($entering) {
            Rwidget_play_transition($this->native, match ($transition) {
                TKTransition::FADE => R_TRANSITION_FADE,
                TKTransition::SLIDE_UP => R_TRANSITION_FLY_UP,
                TKTransition::SLIDE_DOWN => R_TRANSITION_FLY_DOWN,
                TKTransition::SLIDE_LEFT => R_TRANSITION_SLIDE_LEFT,
                TKTransition::SLIDE_RIGHT => R_TRANSITION_SLIDE_RIGHT,
            }, 0.0);
        }
        $self = WeakReference::create($this);
        $start = microtime(true);
        $from = $this->opacity;
        Rtimer_create(1 / 60, R_TRUE, static function (int $timer) use ($self, $generation, $entering, $seconds, $start, $from, $done): void {
            $primitive = $self->get();
            if (is_null($primitive) || $primitive->isRemoved() || $primitive->transition_generation !== $generation) {
                Rtimer_destroy($timer);

                return;
            }
            $t = $seconds > 0 ? min(1.0, (microtime(true) - $start) / $seconds) : 1.0;
            if (! $entering) {
                Rwidget_set_opacity($primitive->native, $from * (1.0 - $t));
            }
            if ($t >= 1.0) {
                Rtimer_destroy($timer);
                if (! is_null($done)) {
                    $done();
                }
                // Back at its opacity for the next time it shows.
                Rwidget_set_opacity($primitive->native, $primitive->opacity);
            }
        }, null);
    }

    protected function applyFill(bool $horizontal, bool $vertical): void
    {
        $this->placeInParent();
    }

    protected function applyAlign(Align $horizontal, Align $vertical): void
    {
        $this->placeInParent();
    }

    /** Have the container re-apply this primitive's slot. */
    protected function placeInParent(): void
    {
        if ($this->parent instanceof RavenContainer) {
            $this->parent->placeChild($this);
        } elseif (is_null($this->parent)) {
            $this->ravenWindow()->placeContent($this);
        }
    }

    protected function applyMinSize(int $width, int $height): void
    {
        Rwidget_set_min_size($this->native, $width, $height);
    }

    protected function applyWatchSize(bool $on): void
    {
        $key = "view.resized.{$this->window->name()}.{$this->path()}";
        if (! $on) {
            $this->session()->unwatch($this);
            $this->session()->forgetLatest($key);

            return;
        }
        $self = WeakReference::create($this);
        $this->session()->watch(
            $this,
            static fn (): array => $self->get()?->nativeSize() ?? [0, 0],
            static function (int $width, int $height) use ($self, $key): void {
                $primitive = $self->get();
                $primitive?->session()->postLatest($key, new ViewResized($primitive->window->name(), $primitive->path(), $primitive->uuid, $width, $height));
            },
        );
    }

    public function nativeSize(): array
    {
        Rwidget_get_rect($this->native, $x, $y, $width, $height);

        return [(int) round($width), (int) round($height)];
    }

    /** The native leaves its parent and is deleted with its descendants. */
    protected function destroyNative(): void
    {
        $this->popup?->close();
        $this->popup = null;
        $this->transition_generation++;
        $this->session()->unwatch($this);
        $this->ravenWindow()->release($this->native);
        if (Rwidget_is_valid($this->native) === R_TRUE) {
            Rwidget_destroy($this->native);
        }
    }

    /** Text-bearing kinds: the font and its size on the control. */
    protected function applyFont(FontSpec $font): void
    {
        Rwidget_set_font($this->control, RavenFonts::font($font));
        Rwidget_set_metric($this->control, R_METRIC_FONT_SIZE, $font->size);
    }

    /** Text-bearing kinds: the text colour; null returns to the theme's. */
    protected function applyTextColor(?Color $color): void
    {
        is_null($color)
            ? Rwidget_clear_color($this->control, R_COLOR_TEXT)
            : Rwidget_set_color($this->control, R_COLOR_TEXT, $color->red, $color->green, $color->blue, $color->alpha);
    }

    protected static function textAlign(TextAlignment $alignment): int
    {
        return match ($alignment) {
            TextAlignment::LEFT => R_TEXT_ALIGN_LEFT,
            TextAlignment::CENTER => R_TEXT_ALIGN_CENTER,
            TextAlignment::RIGHT => R_TEXT_ALIGN_RIGHT,
        };
    }

    /** Whether a native callback is the user's: not an echo of a code-driven write, not from a removed primitive. */
    protected function handling(): bool
    {
        return ! $this->applying && ! $this->removed;
    }

    /** @param Closure(): void $write */
    protected function applying(Closure $write): void
    {
        $this->applying = true;
        try {
            $write();
        } finally {
            $this->applying = false;
        }
    }

    /** Focus from code. A widget Raven does not make focusable takes none. */
    protected function applyFocus(): bool
    {
        Rwidget_focus($this->focusRid());

        return Rwidget_is_focused($this->focusRid()) === R_TRUE;
    }

    protected function nativeHasFocus(): bool
    {
        return Rwidget_is_focused($this->focusRid()) === R_TRUE;
    }

    public function openPopup(): ?RavenContextMenu
    {
        return $this->popup?->isOpen() ? $this->popup : null;
    }

    /** Open $menu at ($x, $y) in the native; false while the window or the primitive is not shown. */
    protected function applyPopupMenu(ContextMenuContract $menu, float $x, float $y): bool
    {
        $window = $this->ravenWindow();
        if (! $menu instanceof ContextMenu || ! $window->isShown() || Rwidget_get_visibility($this->native) !== R_VISIBLE) {
            return false;
        }
        Rwidget_get_rect($this->native, $left, $top, $width, $height);
        $this->popup?->close();
        $this->popup = new RavenContextMenu($menu, $window, $this);
        $this->popup->openAt($left + $x, $top + $y);

        return true;
    }

    protected function ravenWindow(): RavenWindow
    {
        return $this->window instanceof RavenWindow
            ? $this->window
            : throw new ToolkitRuntimeException(static::class.' lives in a RavenWindow, got '.get_class($this->window).'.');
    }

    protected function session(): RavenSession
    {
        return $this->ravenWindow()->session();
    }

    /** The native of a child this driver minted. */
    protected static function nativeOf(TKPrimitive $child): int
    {
        return $child instanceof RavenNative
            ? $child->native()
            : throw new ToolkitRuntimeException("'{$child->path()}' is not a raven primitive.");
    }
}
