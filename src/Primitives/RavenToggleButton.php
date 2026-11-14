<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenButtonFace;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenButtonColors;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Toolkits\Primitives\TKToggleButton;
use WeakReference;

/** Raven has no toggle button: a button that shows pressed in the primary style and flips on a click. */
class RavenToggleButton extends TKToggleButton implements RavenNative
{
    use RavenButtonFace;
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
        RavenButtonColors::applyBackground insteadof RavenPrimitive;
    }
    use RavenButtonColors;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $label, bool $pressed)
    {
        parent::__construct($name, $window, $parent, $placement, $label, $pressed);
        $button = $this->adoptNative(Rbutton_create($label));
        $this->applyStyle();
        $self = WeakReference::create($this);
        Rbutton_set_clicked_callback($button, static function () use ($self): void {
            $primitive = $self->get();
            if (! $primitive?->handling()) {
                return;
            }
            $on = ! $primitive->pressed;
            $primitive->nativeToggled($on);
            $primitive->applyStyle();
            $primitive->session()->post(new Toggled($primitive->window->name(), $primitive->path(), $primitive->uuid, $on));
        }, null);
    }

    protected function applyPressed(bool $pressed): void
    {
        $this->applyStyle();
    }

    protected function applyStyle(): void
    {
        Rbutton_set_style($this->control, match (true) {
            $this->pressed => R_BUTTON_PRIMARY,
            ! $this->bordered => R_BUTTON_FLAT,
            default => R_BUTTON_DEFAULT,
        });
    }

    protected function destroyNative(): void
    {
        $this->destroyFace();
        $this->destroyPrimitive();
    }
}
