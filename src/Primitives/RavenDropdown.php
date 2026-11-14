<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Toolkits\Primitives\TKDropdown;

/** A Raven combo box: Raven's combo box chooses from its items and takes no typing. */
class RavenDropdown extends TKDropdown implements RavenNative
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, array $options, int $selected)
    {
        parent::__construct($name, $window, $parent, $placement, $options, $selected);
        $combo = $this->adoptNative(Rwidget_create(R_WIDGET_COMBO_BOX));
        $this->applyOptions($this->options);
        $this->applySelected($this->selected);
        $self = WeakReference::create($this);
        Rcombo_box_set_changed_callback($combo, static function (int $combo, int $index) use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling() && $index >= 0) {
                $primitive->nativeSelected($index);
                $primitive->session()->post(new SelectionChanged($primitive->window->name(), $primitive->path(), $primitive->uuid, $index, $primitive->options[$index] ?? null));
            }
        }, null);
    }

    protected function applyOptions(array $options): void
    {
        $this->applying(function () use ($options): void {
            Rcombo_box_clear_items($this->control);
            foreach ($options as $option) {
                Rcombo_box_add_item($this->control, (string) $option);
            }
        });
    }

    protected function applySelected(int $index): void
    {
        $this->applying(fn () => Rcombo_box_set_selected($this->control, $index));
    }
}
