<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Toolkits\Primitives\TKCheckbox;

class RavenCheckbox extends TKCheckbox implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $label, bool $checked)
    {
        parent::__construct($name, $window, $parent, $placement, $label, $checked);
        $box = $this->adoptNative(Rcheckbox_create($label));
        Rcheckbox_set_checked($box, $checked ? R_TRUE : R_FALSE);
        $self = WeakReference::create($this);
        Rcheckbox_set_changed_callback($box, static function (int $box, int $checked) use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->nativeToggled($checked === R_TRUE);
                $primitive->session()->post(new Toggled($primitive->window->name(), $primitive->path(), $primitive->uuid, $checked === R_TRUE));
            }
        }, null);
    }

    protected function applyLabel(string $label): void
    {
        Rcheckbox_set_text($this->control, $label);
    }

    protected function applyChecked(bool $checked): void
    {
        $this->applying(fn () => Rcheckbox_set_checked($this->control, $checked ? R_TRUE : R_FALSE));
    }
}
