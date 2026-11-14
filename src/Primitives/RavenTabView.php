<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenContainer;
use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive as Child;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\TabSelected;
use Surface\Contracts\Toolkits\Primitives\TKColumn;
use Surface\Toolkits\Primitives\TKTabView;
use WeakReference;

/** A Raven tab view: one tab per page column; removing the page's column removes its tab. */
class RavenTabView extends TKTabView implements RavenContainer
{
    use RavenPrimitive;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement)
    {
        parent::__construct($name, $window, $parent, $placement);
        $tabs = $this->adoptNative(Rwidget_create(R_WIDGET_TAB_VIEW));
        $self = WeakReference::create($this);
        Rtab_view_set_changed_callback($tabs, static function (int $tabs, int $index) use ($self): void {
            $primitive = $self->get();
            if (! $primitive?->handling() || $index < 0) {
                return;
            }
            $name = $primitive->pageAt($index);
            if (! is_null($name)) {
                $primitive->session()->post(new TabSelected($primitive->window->name(), $primitive->path(), $primitive->uuid, $name));
            }
        }, null);
    }

    public function placeChild(Child&RavenNative $child): void {}

    /** A tab is a child of the tab view with a slot title: one at an index is an inserted child. */
    protected function insertPage(TKColumn $page, string $title, ?int $index): void
    {
        $this->applying(function () use ($page, $title, $index): void {
            if (is_null($index)) {
                Rtab_view_add_tab($this->control, $title, self::nativeOf($page));

                return;
            }
            Rwidget_insert_child($this->control, self::nativeOf($page), $index);
            Rslot_set_title(self::nativeOf($page), $title);
        });
    }

    protected function applyTitle(TKColumn $page, string $title): void
    {
        Rtab_view_set_tab_title($this->control, self::nativeOf($page), $title);
    }

    protected function applySelect(TKColumn $page): void
    {
        $this->applying(fn () => Rtab_view_show_tab($this->control, self::nativeOf($page)));
    }

    protected function nativeSelected(): ?string
    {
        $index = Rtab_view_get_active($this->control);

        return $index < 0 ? null : $this->pageAt($index);
    }

    /** The name of the page whose column is the tab at $index. */
    public function pageAt(int $index): ?string
    {
        $content = Rtab_view_get_tab($this->control, $index);
        foreach ($this->children as $name => $page) {
            if (self::nativeOf($page) === $content) {
                return $name;
            }
        }

        return null;
    }
}
