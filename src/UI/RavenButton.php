<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\UI\TKButton;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\ButtonStyle;
use Surface\Contracts\Toolkits\UI\IconPosition;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Jovian\Toolkits\Raven\UI\Concerns\RavenText;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;
use Jovian\Toolkits\Raven\UI\Concerns\RavenButtonSurface;

/**
 * A Raven button. It paints its own surface, so it is its own native with no border around
 * it: its background is the button's control colours and its corner radius the button's
 * corner metric. It takes keyboard focus as Raven makes every button do; its clicked callback
 * posts ButtonClicked (Raven calls it for a click, Space or Enter, never while the button or a
 * container above it is disabled).
 *
 * Its content is the label Raven gives it, or, with an icon, what Raven's header suggests for
 * one (any single child, "e.g. an HBox with an icon"): a horizontal box holding an image and
 * that same label, the image before it or, for a trailing icon, after it. Its
 * font is the button's (Rwidget_set_font), its size and alignment the label's, its text colour
 * the button's R_COLOR_TEXT, which the label takes. BORDERLESS and LINK have no line: Raven's
 * flat button with no fill in any state, or, with a background, Raven's default button filled
 * with it and its line taken away (RavenButtonSurface). LINK's text is the theme's R_COLOR_LINK
 * for the button.
 * Padding is the content's own: Raven's button pads its child by the metrics R_METRIC_PADDING_X
 * and _Y (the same on both sides), so a padding is those metrics at 0 and each side on the
 * content's slot (Rslot_set_padding), which the button's measure adds to its natural size. The
 * height still has Raven's R_METRIC_CONTROL_HEIGHT as its floor. The content is the label, or
 * the icon box: its padding follows it when the icon comes or goes.
 * Raven has no default or cancel button: a role is not shown.
 */
class RavenButton extends TKButton
{
    use RavenButtonSurface, RavenNative {
        RavenButtonSurface::applyBackground insteadof RavenNative;
        RavenButtonSurface::applyCornerRadius insteadof RavenNative;
        RavenButtonSurface::applyBorder insteadof RavenNative;
        RavenButtonSurface::applyCorners insteadof RavenNative;
        releaseNative as private releaseWidget;
    }
    use RavenText;

    /** The text colour set; null is the theme's (the link colour in LINK style). */
    private ?Color $text_color = null;

    /** Null is Raven's: the content centred. */
    private ?TextAlignment $text_alignment = null;

    /** The size of the label's text; null until a font sets one. */
    private ?float $text_size = null;

    /** The box holding the icon and the label; 0 without an icon. */
    private int $icon_box = 0;

    /** The image in the icon box; 0 without an icon. */
    private int $icon_image = 0;

    /** A texture read from the icon file, this button's own; R_INVALID_ID for none. */
    private int $icon_texture = R_INVALID_ID;

    /** @var array<string, true>|null the names of Raven's icons, once */
    private static ?array $symbols = null;

    /**
     * The callback holds the primitive weakly: a strong capture would be a cycle through
     * ext-raven's held callback that PHP's collector cannot see.
     */
    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rbutton_create($this->label()), wrapped: false);

        $self = WeakReference::create($this);
        Rbutton_set_clicked_callback($this->control, static function (int $button, mixed $data) use ($self): void {
            $self->get()?->clicked();
        }, null);
    }

    /**
     * The callback goes first: ext-raven would otherwise hold it until it next sweeps. The
     * icon's texture goes after the widgets showing it.
     */
    protected function releaseNative(): void
    {
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rbutton_set_clicked_callback($this->control, null, null);
        }

        $this->releaseWidget();
        $this->icon_box = 0;
        $this->icon_image = 0;
        $this->dropIconTexture();
    }

    /**
     * Padding set: Raven's own padding metrics at 0 and each side on the content's slot. None
     * gives Raven's metrics back.
     */
    protected function applyPadding(Insets $padding): void
    {
        if ($padding->isZero()) {
            Rwidget_clear_metric($this->control, R_METRIC_PADDING_X);
            Rwidget_clear_metric($this->control, R_METRIC_PADDING_Y);
        } else {
            Rwidget_set_metric($this->control, R_METRIC_PADDING_X, 0.0);
            Rwidget_set_metric($this->control, R_METRIC_PADDING_Y, 0.0);
        }

        $this->padContent();
    }

    /**
     * The padding on the slot of whatever is the button's content now.
     */
    private function padContent(): void
    {
        $padding = $this->padding();
        Rslot_set_padding(Rwidget_get_child($this->control, 0), $padding->left, $padding->top, $padding->right, $padding->bottom);
    }

    /**
     * The label itself: Rbutton_set_text would replace an icon box with a second label.
     */
    protected function applyLabel(string $label): void
    {
        Rlabel_set_text($this->labelWidget(), $label);
    }

    protected function applyStyle(ButtonStyle $style): void
    {
        // Raven has no help button: HELP is a framed button, its label shown.
        if ($style->framed()) {
            Rbutton_set_style($this->control, R_BUTTON_DEFAULT);
        }
        $this->paintSurface();
        $this->paintText();
    }

    /**
     * The family and weight for the button's texts, the size on its label (and its icon).
     */
    protected function applyFont(FontSpec $font): void
    {
        Rwidget_set_font($this->control, self::ravenFont($font));
        Rlabel_set_text_size($this->labelWidget(), $font->size);
        $this->text_size = $font->size;

        if ($this->icon_image !== 0) {
            $this->sizeIcon($this->icon_image);
        }
    }

    protected function applyTextColor(?Color $color): void
    {
        $this->text_color = $color;
        $this->paintText();
    }

    /**
     * The content's place across the button, and the label's lines within it.
     */
    protected function applyAlignment(TextAlignment $alignment): void
    {
        $this->text_alignment = $alignment;
        $this->placeContent();
    }

    /**
     * The file's picture, else Raven's icon of that name (a Lucide line icon, drawn in the
     * text's colour), before the label. A name Raven ships no icon for shows none.
     *
     * @throws ToolkitException When Raven reads no picture in the file.
     */
    protected function applyIcon(?string $file, ?string $symbol): void
    {
        $owned = R_INVALID_ID;
        $texture = match (true) {
            ! is_null($file) => $owned = self::readIcon($file),
            ! is_null($symbol) && self::ravenHasIcon($symbol) => Ricon_get($symbol),
            default => R_INVALID_ID,
        };

        $texture === R_INVALID_ID ? $this->dropIconBox() : $this->showIcon($texture);
        $this->dropIconTexture();
        $this->icon_texture = $owned;
    }

    /**
     * The label before the image in the icon box for TRAILING, after it for LEADING.
     */
    protected function applyIconPosition(IconPosition $position): void
    {
        if ($this->icon_box !== 0) {
            $this->orderIconBox();
        }
    }

    protected function surfaceIsFlat(): bool
    {
        return ! $this->style()->framed();
    }

    /**
     * The label Raven made: the button's child, or the one in the icon box beside the image.
     */
    private function labelWidget(): int
    {
        if ($this->icon_box === 0) {
            return Rwidget_get_child($this->control, 0);
        }

        $first = Rwidget_get_child($this->icon_box, 0);

        return $first === $this->icon_image ? Rwidget_get_child($this->icon_box, 1) : $first;
    }

    /**
     * Raven draws an HBox's children in order: the image first for LEADING, last for TRAILING.
     * Rwidget_insert_child moves a child already there.
     */
    private function orderIconBox(): void
    {
        Rwidget_insert_child($this->icon_box, $this->icon_image, $this->iconPosition() === IconPosition::TRAILING ? 1 : 0);
    }

    /**
     * The colour set, else in LINK style the theme's link colour for this button, else the
     * theme's text colour.
     */
    private function paintText(): void
    {
        if (! is_null($this->text_color)) {
            $color = $this->text_color;
            Rwidget_set_color($this->control, R_COLOR_TEXT, $color->red, $color->green, $color->blue, $color->alpha);

            return;
        }

        if ($this->style() === ButtonStyle::LINK) {
            Rwidget_get_color($this->control, R_COLOR_LINK, $red, $green, $blue, $alpha);
            Rwidget_set_color($this->control, R_COLOR_TEXT, (float) $red, (float) $green, (float) $blue, (float) $alpha);

            return;
        }

        Rwidget_clear_color($this->control, R_COLOR_TEXT);
    }

    /**
     * The content's slot across the button and the label's text alignment; centred, as Raven
     * places the label it makes, until an alignment is set.
     */
    private function placeContent(): void
    {
        $content = Rwidget_get_child($this->control, 0);

        Rslot_set_halign($content, match ($this->text_alignment) {
            TextAlignment::LEFT => R_HALIGN_LEFT,
            TextAlignment::RIGHT => R_HALIGN_RIGHT,
            TextAlignment::CENTER, null => R_HALIGN_CENTER,
        });
        Rslot_set_valign($content, R_VALIGN_CENTER);

        if (! is_null($this->text_alignment)) {
            Rlabel_set_text_align($this->labelWidget(), self::ravenAlignment($this->text_alignment));
        }
    }

    /**
     * The icon box in the button, made the first time: the image and the label moved into it,
     * in the icon position's order, the theme's spacing between them.
     */
    private function showIcon(int $texture): void
    {
        if ($this->icon_box === 0) {
            $label = Rwidget_get_child($this->control, 0);
            $box = Rwidget_create(R_WIDGET_HBOX);
            Rpanel_set_spacing($box, Rwidget_get_metric($this->control, R_METRIC_SPACING));

            $image = Rwidget_create(R_WIDGET_IMAGE);
            Rimage_set_stretch($image, R_STRETCH_FIT);
            Rwidget_add_child($box, $image);
            Rslot_set_valign($image, R_VALIGN_CENTER);

            Rwidget_remove_child($this->control, $label);
            Rwidget_add_child($box, $label);
            Rslot_set_valign($label, R_VALIGN_CENTER);
            // the padding was the label's slot's: it belongs to the box now
            Rslot_set_padding($label, 0, 0, 0, 0);

            Rwidget_add_child($this->control, $box);
            $this->icon_box = $box;
            $this->icon_image = $image;
            $this->orderIconBox();
            $this->placeContent();
            $this->padContent();
        }

        Rimage_set_texture($this->icon_image, $texture);
        $this->sizeIcon($this->icon_image);
    }

    /**
     * The label back in the button, the box and its image gone.
     */
    private function dropIconBox(): void
    {
        if ($this->icon_box === 0) {
            return;
        }

        $box = $this->icon_box;
        $label = $this->labelWidget();
        Rwidget_remove_child($box, $label);
        Rwidget_remove_child($this->control, $box);
        Rwidget_destroy($box);
        $this->icon_box = 0;
        $this->icon_image = 0;

        Rwidget_add_child($this->control, $label);
        $this->placeContent();
        $this->padContent();
    }

    /**
     * Square, a quarter taller than the label's text.
     */
    private function sizeIcon(int $image): void
    {
        $side = ceil(($this->text_size ?? Rwidget_get_metric($this->control, R_METRIC_FONT_SIZE)) * 1.25);
        Rwidget_set_size($image, $side, $side);
    }

    private function dropIconTexture(): void
    {
        if ($this->icon_texture !== R_INVALID_ID && Rtexture_is_valid($this->icon_texture) === R_TRUE) {
            Rtexture_destroy($this->icon_texture);
        }
        $this->icon_texture = R_INVALID_ID;
    }

    /**
     * @throws ToolkitException When Raven reads no picture in it.
     */
    private static function readIcon(string $file): int
    {
        $texture = Rtexture_create_from_file($file);

        return Rtexture_is_valid($texture) === R_TRUE ? $texture : throw new ToolkitException("'{$file}' is not an image Raven reads.");
    }

    /**
     * Whether Raven ships an icon of that name ("search", or "raven:search").
     */
    private static function ravenHasIcon(string $name): bool
    {
        if (is_null(self::$symbols)) {
            self::$symbols = [];
            for ($index = 0, $count = Ricon_get_count(); $index < $count; $index++) {
                self::$symbols[(string) Ricon_get_name($index)] = true;
            }
        }

        return isset(self::$symbols[str_starts_with($name, 'raven:') ? substr($name, 6) : $name]);
    }
}
