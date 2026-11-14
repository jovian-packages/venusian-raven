<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenContainer;
use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive as Child;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException;
use Surface\Toolkits\Primitives\TKScrollView;

/** A Raven scroll box holding the one child: it scrolls vertically, horizontally, or both. */
class RavenScrollView extends TKScrollView implements RavenContainer
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement)
    {
        parent::__construct($name, $window, $parent, $placement);
        $this->adoptNative(Rwidget_create(R_WIDGET_SCROLL_BOX));
    }

    public function placeChild(Child&RavenNative $child): void
    {
        [$horizontal, $vertical] = $child->fills();
        [$halign, $valign] = $child->ravenAlign();
        Rslot_set_halign($child->native(), $horizontal ? R_HALIGN_FILL : $halign);
        Rslot_set_valign($child->native(), $vertical ? R_VALIGN_FILL : $valign);
    }

    protected function insertNative(Child $child): void
    {
        Rwidget_add_child($this->control, self::nativeOf($child));
        if ($child instanceof RavenNative) {
            $this->placeChild($child);
        }
    }

    /**
     * @throws RavenIncompatibilityException With neither axis: a Raven scroll box scrolls at least one.
     */
    protected function applyScrollbars(bool $horizontal, bool $vertical): void
    {
        if (! $horizontal && ! $vertical) {
            throw RavenIncompatibilityException::feature('A scroll view with no scroll bars', 'a Raven scroll box always scrolls one axis or both.');
        }
        Rscroll_box_set_scroll_both($this->control, $horizontal && $vertical ? R_TRUE : R_FALSE);
        Rscroll_box_set_orientation($this->control, $vertical ? R_VERTICAL : R_HORIZONTAL);
    }
}
