<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenButtonColors;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Jovian\Toolkits\Raven\Windows\RavenContextMenu;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Toolkits\Menus\ContextMenu as ContextMenuContract;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Menus\ContextMenu;
use Surface\Toolkits\Primitives\TKMenuButton;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;

/** A Raven button opening its menu under itself; the menu keeps its toggles and radios. */
class RavenMenuButton extends TKMenuButton implements RavenNative
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
        RavenButtonColors::applyBackground insteadof RavenPrimitive;
    }
    use RavenButtonColors;

    protected int $texture = 0;

    protected ?RavenContextMenu $open = null;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $label, ContextMenuContract $menu)
    {
        parent::__construct($name, $window, $parent, $placement, $label, $menu);
        $button = $this->adoptNative(Rbutton_create($label));
        $self = WeakReference::create($this);
        Rbutton_set_clicked_callback($button, static fn () => $self->get()?->openMenu(), null);
    }

    public function openMenu(): void
    {
        if (! $this->handling() || ! $this->menu instanceof ContextMenu) {
            return;
        }
        $this->open?->close();
        $this->open = new RavenContextMenu($this->menu, $this->ravenWindow(), $this);
        Rwidget_get_rect($this->native, $x, $y, $width, $height);
        $this->open->openAt($x, $y + $height);
    }

    protected function applyLabel(string $label): void
    {
        $this->applyFace();
    }

    protected function applyImage(?Framebuffer $image, float $scale): void
    {
        $this->applyFace();
    }

    /** An image shows before the label, inside the button. */
    protected function applyFace(): void
    {
        Rwidget_clear_children($this->control);
        if ($this->texture !== 0) {
            Rtexture_destroy($this->texture);
            $this->texture = 0;
        }
        if (is_null($this->image)) {
            Rbutton_set_text($this->control, $this->label);

            return;
        }
        Rbutton_set_text($this->control, '');
        $row = Rwidget_add_child($this->control, Rwidget_create(R_WIDGET_HBOX));
        Rpanel_set_spacing($row, 6);
        Rslot_set_halign($row, R_HALIGN_CENTER);
        $this->texture = Rtexture_create_from_pixels($this->image->toRgba8(), $this->image->viewportWidth(), $this->image->viewportHeight());
        $picture = Rwidget_add_child($row, Rwidget_create(R_WIDGET_IMAGE));
        Rimage_set_texture($picture, $this->texture);
        $width = $this->image->viewportWidth() / $this->image_scale;
        $height = $this->image->viewportHeight() / $this->image_scale;
        Rwidget_set_min_size($picture, $width, $height);
        Rwidget_set_max_size($picture, $width, $height);
        if ($this->label !== '') {
            Rwidget_add_child($row, Rlabel_create($this->label));
        }
    }

    /** @throws ToolkitRuntimeException When the menu is not a parsed ContextMenu. */
    protected function applyMenu(ContextMenuContract $menu): void
    {
        if (! $menu instanceof ContextMenu) {
            throw new ToolkitRuntimeException(get_class($menu).' is not a menu parsed by Surface\Toolkits\Menus\ContextMenu.');
        }
        $this->open?->close();
        $this->open = null;
    }

    protected function applyBordered(bool $on): void
    {
        Rbutton_set_style($this->control, $on ? R_BUTTON_DEFAULT : R_BUTTON_FLAT);
    }

    protected function destroyNative(): void
    {
        $this->open?->close();
        $this->open = null;
        $this->destroyPrimitive();
        if ($this->texture !== 0) {
            Rtexture_destroy($this->texture);
        }
    }
}
