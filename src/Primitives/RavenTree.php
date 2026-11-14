<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Mail\Views\NodeActivated;
use Surface\Contracts\Toolkits\Mail\Views\NodeExpanded;
use Surface\Contracts\Toolkits\Mail\Views\NodeSelected;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\TreeNode;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Toolkits\Primitives\TKTree;
use WeakReference;

/**
 * A Raven tree view. Raven's tree items carry one text: a node's cells after the first follow it
 * on the same line, and the first column's colour is the item's. Raven reports no expansion, so
 * expansions are compared after each pump and the user's post NodeExpanded.
 */
class RavenTree extends TKTree implements RavenNative
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
    }

    /** @var array<string, int> node id => tree item */
    protected array $items = [];

    /** @var array<int, string> tree item => node id */
    protected array $ids = [];

    /** @var array<string, bool> the expansions last seen */
    protected array $seen = [];

    protected ?string $known_selected = null;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, array $columns, array $nodes)
    {
        parent::__construct($name, $window, $parent, $placement, $columns, $nodes);
        $tree = $this->adoptNative(Rwidget_create(R_WIDGET_TREE_VIEW));
        $this->applyNodes($this->nodes);
        $self = WeakReference::create($this);
        Rtree_view_set_selection_changed_callback($tree, static fn () => $self->get()?->selectionChanged(), null);
        Rtree_view_set_activated_callback($tree, static function (int $tree, int $item) use ($self): void {
            $primitive = $self->get();
            $id = $primitive?->ids[$item] ?? null;
            if ($primitive?->handling() && ! is_null($id)) {
                $primitive->session()->post(new NodeActivated($primitive->window->name(), $primitive->path(), $primitive->uuid, $id));
            }
        }, null);
        $this->session()->afterPump("tree.{$this->uuid}", static fn () => $self->get()?->compareExpansions());
    }

    protected function applyNodes(array $nodes): void
    {
        $this->applying(function () use ($nodes): void {
            Rtree_view_clear_items($this->control);
            $this->items = [];
            $this->ids = [];
            $this->seen = [];
            $this->add($nodes, 0);
        });
    }

    /** @param list<TreeNode> $nodes */
    protected function add(array $nodes, int $parent): void
    {
        foreach ($nodes as $node) {
            $texts = array_values(array_filter($this->cellTexts($node), static fn (string $text): bool => $text !== ''));
            $item = Rtree_view_add_item($this->control, $parent, implode('    ', $texts));
            $color = $this->cellColors($node)[0] ?? null;
            if (! is_null($color)) {
                Rtree_item_set_text_color($item, $color->red, $color->green, $color->blue, $color->alpha);
            }
            $this->items[$node->id] = $item;
            $this->ids[$item] = $node->id;
            $this->add($node->children, $item);
            if ($node->children !== []) {
                $open = $this->isExpanded($node->id);
                Rtree_item_set_expanded($item, $open ? R_TRUE : R_FALSE);
                $this->seen[$node->id] = $open;
            }
        }
    }

    protected function applyExpanded(string $id, bool $expanded): void
    {
        if (isset($this->items[$id])) {
            Rtree_item_set_expanded($this->items[$id], $expanded ? R_TRUE : R_FALSE);
            $this->seen[$id] = $expanded;
        }
    }

    protected function applySelected(?string $id): void
    {
        $this->applying(function () use ($id): void {
            $item = is_null($id) ? 0 : ($this->items[$id] ?? 0);
            if ($item === 0) {
                Rtree_view_clear_selection($this->control);
            } else {
                Rtree_view_set_selected_item($this->control, $item);
                Rtree_view_scroll_to_item($this->control, $item);
            }
        });
        $this->known_selected = $this->nativeSelected();
    }

    protected function nativeSelected(): ?string
    {
        $item = Rtree_view_get_selected_item($this->control);

        return $this->ids[$item] ?? null;
    }

    public function selectionChanged(): void
    {
        if (! $this->handling()) {
            return;
        }
        $selected = $this->nativeSelected();
        if ($selected === $this->known_selected) {
            return;
        }
        $this->known_selected = $selected;
        $this->session()->post(new NodeSelected($this->window->name(), $this->path(), $this->uuid, $selected));
    }

    public function compareExpansions(): void
    {
        if ($this->removed) {
            return;
        }
        foreach ($this->seen as $id => $was) {
            $now = Rtree_item_is_expanded($this->items[$id]) === R_TRUE;
            if ($now === $was) {
                continue;
            }
            $this->seen[$id] = $now;
            if ($this->nativeNodeExpanded($id, $now)) {
                $this->session()->post(new NodeExpanded($this->window->name(), $this->path(), $this->uuid, $id, $now));
            }
        }
    }

    protected function destroyNative(): void
    {
        $this->session()->afterPump("tree.{$this->uuid}", null);
        $this->destroyPrimitive();
    }
}
