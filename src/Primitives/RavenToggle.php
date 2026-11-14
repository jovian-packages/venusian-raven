<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Toolkits\Primitives\TKToggle;

/** Raven's checkbox in its switch style, with no text. */
class RavenToggle extends TKToggle implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, bool $on)
    {
        parent::__construct($name, $window, $parent, $placement, $on);
        $switch = $this->adoptNative(Rcheckbox_create(''));
        Rcheckbox_set_style($switch, R_CHECKBOX_SWITCH);
        Rcheckbox_set_checked($switch, $on ? R_TRUE : R_FALSE);
        $self = WeakReference::create($this);
        Rcheckbox_set_changed_callback($switch, static function (int $switch, int $checked) use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->nativeToggled($checked === R_TRUE);
                $primitive->session()->post(new Toggled($primitive->window->name(), $primitive->path(), $primitive->uuid, $checked === R_TRUE));
            }
        }, null);
    }

    protected function applyOn(bool $on): void
    {
        $this->applying(fn () => Rcheckbox_set_checked($this->control, $on ? R_TRUE : R_FALSE));
    }
}
