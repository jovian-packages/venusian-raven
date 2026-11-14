<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenButtonFace;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenButtonColors;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Contracts\Toolkits\Primitives\ButtonRole;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKButton;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;

/** A Raven button. The default role is Raven's primary style; unbordered is its flat style. */
class RavenButton extends TKButton implements RavenNative
{
    use RavenButtonFace;
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
        RavenButtonColors::applyBackground insteadof RavenPrimitive;
    }
    use RavenButtonColors;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $label)
    {
        parent::__construct($name, $window, $parent, $placement, $label);
        $button = $this->adoptNative(Rbutton_create($label));
        $self = WeakReference::create($this);
        // A disabled Raven button does not call back: no mail.
        Rbutton_set_clicked_callback($button, static function () use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->session()->post(new ButtonClicked($primitive->window->name(), $primitive->path(), $primitive->uuid));
            }
        }, null);
    }

    protected function applyRole(?ButtonRole $role): void
    {
        $this->applyStyle();
    }

    protected function applyStyle(): void
    {
        Rbutton_set_style($this->control, match (true) {
            ! $this->bordered => R_BUTTON_FLAT,
            $this->role === ButtonRole::DEFAULT => R_BUTTON_PRIMARY,
            default => R_BUTTON_DEFAULT,
        });
    }

    /** Raven centres a button's own text: another alignment places a face label instead. */
    protected function applyAlignment(TextAlignment $alignment): void
    {
        $this->applyFace();
    }

    protected function destroyNative(): void
    {
        $this->destroyFace();
        $this->destroyPrimitive();
    }
}
