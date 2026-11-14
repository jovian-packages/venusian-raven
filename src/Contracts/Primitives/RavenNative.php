<?php

namespace Jovian\Toolkits\Raven\Contracts\Primitives;

/**
 * Every raven primitive: the widget it puts in its parent, the widget that takes focus, and its
 * fill and align for the parent's slot.
 */
interface RavenNative
{
    /** The outer widget (a border carrying background, corner radius and padding): what the parent holds. */
    public function native(): int;

    /** The widget that takes keyboard focus. */
    public function focusRid(): int;

    /**
     * @return array{bool, bool} fill horizontal, vertical
     */
    public function fills(): array;

    /**
     * The align() per axis as Raven's slot alignments.
     * @return array{int, int} RHAlign, RVAlign
     */
    public function ravenAlign(): array;
}
