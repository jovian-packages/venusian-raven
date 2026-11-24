<?php

namespace Jovian\Toolkits\Raven\UI;

use WeakReference;
use Surface\Toolkits\UI\TKTree;
use Surface\Contracts\Toolkits\UI\TreeNode;
use Jovian\Toolkits\Raven\UI\Concerns\RavenNative;

/**
 * A Raven tree view. It shows one text per row, so further columns follow the first on it.
 */
class RavenTree extends TKTree
{
    use RavenNative {
        releaseNative as private releaseWidget;
    }

    private int $watching = 0;

    /** @var array<string, int> node id => tree item */
    protected array $items = [];

    /** @var array<int, string> tree item => node id */
    protected array $ids = [];

    /** @var array<string, bool> the expansions last seen */
    protected array $seen = [];

    protected function bootstrapNative(): void
    {
        $this->adoptNative(Rwidget_create(R_WIDGET_TREE_VIEW));
        $this->write(fn () => $this->reload());

        $self = WeakReference::create($this);
        Rtree_view_set_selection_changed_callback($this->control, static function () use ($self): void {
            $self->get()?->selectionChanged();
        }, null);
        Rtree_view_set_activated_callback($this->control, static function (int $tree, int $item) use ($self): void {
            $view = $self->get();
            $id = $view?->ids[$item] ?? null;
            if (! is_null($id)) {
                $view->activatedNode($id);
            }
        }, null);
        // Raven has no callback for opening and closing: a timer compares.
        $this->watching = Rtimer_create(1 / 30, R_TRUE, static function (int $timer) use ($self): void {
            $tree = $self->get();
            if (is_null($tree) || $tree->isRemoved()) {
                Rtimer_destroy($timer);

                return;
            }
            $tree->compareExpansions();
        }, null);
    }

    protected function applyNodes(array $nodes): void
    {
        $this->write(function () use ($nodes): void {
            Rtree_view_clear_items($this->control);
            $this->items = [];
            $this->ids = [];
            $this->seen = [];
            $this->add($nodes, R_INVALID_ID);
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
        $this->write(function () use ($id): void {
            $item = is_null($id) ? R_INVALID_ID : ($this->items[$id] ?? R_INVALID_ID);
            if ($item === R_INVALID_ID) {
                Rtree_view_clear_selection($this->control);
            } else {
                Rtree_view_set_selected_item($this->control, $item);
                Rtree_view_scroll_to_item($this->control, $item);
            }
        });
    }

    private function selectedInNative(): ?string
    {
        $item = Rtree_view_get_selected_item($this->control);

        return $this->ids[$item] ?? null;
    }

    protected function selectionChanged(): void
    {
        if (! $this->isRemoved() && ! $this->isWriting()) {
            $this->choseNode($this->selectedInNative());
        }
    }

    protected function compareExpansions(): void
    {
        foreach ($this->seen as $id => $was) {
            $now = Rtree_item_is_expanded($this->items[$id]) === R_TRUE;
            if ($now !== $was) {
                $this->seen[$id] = $now;
                $this->openedNode((string) $id, $now);
            }
        }
    }

    protected function releaseNative(): void
    {
        if ($this->watching !== 0 && Rtimer_is_valid($this->watching) === R_TRUE) {
            Rtimer_destroy($this->watching);
        }
        $this->watching = 0;
        if ($this->control !== 0 && Rwidget_is_valid($this->control) === R_TRUE) {
            Rtree_view_set_selection_changed_callback($this->control, null, null);
            Rtree_view_set_activated_callback($this->control, null, null);
        }
        $this->releaseWidget();
    }
}
