<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenContainer;
use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive as Child;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\SplitMoved;
use Surface\Toolkits\Primitives\TKSplitView;
use WeakReference;

/**
 * A Raven splitter: panes side by side (horizontal) or stacked, with dividers the user drags.
 * Positions are each divider's place as a fraction of the panes' length; Raven reports no drag,
 * so the positions are compared after each pump and a change posts SplitMoved.
 */
class RavenSplitView extends TKSplitView implements RavenContainer
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
    }

    /** @var list<float> the positions last seen */
    protected array $seen = [];

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, bool $horizontal)
    {
        parent::__construct($name, $window, $parent, $placement, $horizontal);
        $splitter = $this->adoptNative(Rwidget_create(R_WIDGET_SPLITTER));
        Rsplitter_set_orientation($splitter, $horizontal ? R_HORIZONTAL : R_VERTICAL);
        $self = WeakReference::create($this);
        $this->session()->afterPump("split.{$this->uuid}", static fn () => $self->get()?->compare());
    }

    /** Each pane takes the share of the length its positions give it. */
    public function placeChild(Child&RavenNative $child): void {}

    protected function insertNative(Child $child): void
    {
        Rwidget_add_child($this->control, self::nativeOf($child));
        Rslot_set_fill(self::nativeOf($child), 1.0);
        $this->seen = $this->nativePositions();
    }

    protected function nativePositions(): array
    {
        $lengths = [];
        foreach ($this->children as $child) {
            Rwidget_get_rect(self::nativeOf($child), $x, $y, $width, $height);
            $lengths[] = $this->horizontal ? $width : $height;
        }
        $total = array_sum($lengths);
        if (count($lengths) < 2 || $total <= 0) {
            return array_fill(0, max(0, count($lengths) - 1), 0.0);
        }
        $positions = [];
        $run = 0.0;
        foreach (array_slice($lengths, 0, -1) as $length) {
            $run += $length;
            $positions[] = round($run / $total, 4);
        }

        return $positions;
    }

    protected function applyPositions(array $positions): void
    {
        $children = array_values($this->children);
        $from = 0.0;
        foreach ([...$positions, 1.0] as $i => $to) {
            if (isset($children[$i])) {
                Rslot_set_fill(self::nativeOf($children[$i]), max(0.0001, $to - $from));
            }
            $from = $to;
        }
        $this->seen = $positions;
    }

    public function compare(): void
    {
        if ($this->removed || count($this->children) < 2) {
            return;
        }
        $now = $this->nativePositions();
        if ($now !== $this->seen && array_sum($now) > 0) {
            $this->seen = $now;
            $this->session()->postLatest("view.split.{$this->window->name()}.{$this->path()}", new SplitMoved($this->window->name(), $this->path(), $this->uuid, $now));
        }
    }

    protected function destroyNative(): void
    {
        $this->session()->afterPump("split.{$this->uuid}", null);
        $this->destroyPrimitive();
    }
}
