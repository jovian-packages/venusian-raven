<?php

namespace Jovian\Toolkits\Raven\Primitives\Concerns;

use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Toolkits\Primitives\ImagePosition;
use Surface\Contracts\Toolkits\Styling\TextAlignment;

/**
 * A button's face: Raven buttons have no icon and centre their text, but they hold child
 * content. A button with an image holds a row (image leading) or a column (image above) of a
 * Raven image and a label; a button whose text is not centred holds the label alone, placed by
 * the alignment. Otherwise the face is the button's own text.
 */
trait RavenButtonFace
{
    /** The face content while the button shows an image: [box, image widget, texture]. */
    private ?array $face = null;

    protected function applyLabel(string $label): void
    {
        $this->applyFace();
    }

    protected function applyImage(?Framebuffer $image, ImagePosition $position, float $scale): void
    {
        $this->applyFace();
    }

    /** Raven's flat style is a button with no bezel. */
    protected function applyBordered(bool $on): void
    {
        $this->applyStyle();
    }

    protected function applyFace(): void
    {
        $this->dropFace();
        $halign = match (property_exists($this, 'alignment') ? $this->alignment : TextAlignment::CENTER) {
            TextAlignment::LEFT => R_HALIGN_LEFT,
            TextAlignment::RIGHT => R_HALIGN_RIGHT,
            TextAlignment::CENTER => R_HALIGN_CENTER,
        };
        if (is_null($this->image) && $halign === R_HALIGN_CENTER) {
            Rbutton_set_text($this->control, $this->label);

            return;
        }
        Rbutton_set_text($this->control, '');
        $box = Rwidget_add_child($this->control, Rwidget_create($this->image_position === ImagePosition::ABOVE ? R_WIDGET_VBOX : R_WIDGET_HBOX));
        Rpanel_set_spacing($box, $this->image_position === ImagePosition::ABOVE ? 2 : 6);
        Rslot_set_halign($box, $halign);
        if (is_null($this->image)) {
            $text = Rwidget_add_child($box, Rlabel_create($this->label));
            Rslot_set_valign($text, R_VALIGN_CENTER);
            $this->face = [$box, 0, 0];

            return;
        }
        $texture = Rtexture_create_from_pixels($this->image->toRgba8(), $this->image->viewportWidth(), $this->image->viewportHeight());
        $picture = Rwidget_add_child($box, Rwidget_create(R_WIDGET_IMAGE));
        Rimage_set_texture($picture, $texture);
        Rimage_set_stretch($picture, R_STRETCH_FIT);
        $width = $this->image->viewportWidth() / $this->image_scale;
        $height = $this->image->viewportHeight() / $this->image_scale;
        Rwidget_set_min_size($picture, $width, $height);
        Rwidget_set_max_size($picture, $width, $height);
        Rslot_set_halign($picture, R_HALIGN_CENTER);
        Rslot_set_valign($picture, R_VALIGN_CENTER);
        if ($this->image_position !== ImagePosition::ONLY) {
            $text = Rwidget_add_child($box, Rlabel_create($this->label));
            Rslot_set_halign($text, R_HALIGN_CENTER);
            Rslot_set_valign($text, R_VALIGN_CENTER);
        }
        Rwidget_set_tooltip($this->control, $this->image_position === ImagePosition::ONLY ? $this->label : null);
        $this->face = [$box, $picture, $texture];
    }

    private function dropFace(): void
    {
        if (is_null($this->face)) {
            return;
        }
        [$box, , $texture] = $this->face;
        if (Rwidget_is_valid($box) === R_TRUE) {
            Rwidget_destroy($box);
        }
        if ($texture !== 0) {
            Rtexture_destroy($texture);
        }
        $this->face = null;
    }

    protected function destroyFace(): void
    {
        $this->dropFace();
    }
}
