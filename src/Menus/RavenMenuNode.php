<?php

namespace Jovian\Toolkits\Raven\Menus;

use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\AboutPanel;
use Surface\Contracts\Toolkits\Menus\BarNode;
use Surface\Contracts\Toolkits\Menus\BarNodeKind;
use Surface\Contracts\Toolkits\Menus\StandardKind;

/**
 * What a RavenMenuBar made for one node.
 *
 * @internal
 */
final class RavenMenuNode
{
    public readonly BarNodeKind $kind;

    public readonly ?StandardKind $standard;

    /** A standard item the application answers: Raven does none of its work. */
    public readonly bool $replaced;

    /** The menu macOS shows first, under the application's name. */
    public readonly bool $application;

    public readonly ?AboutPanel $panel;

    /**
     * Raven widgets: a menu, a line or an item; a choice's one item per option; or none, a kind
     * Raven has its own item for in the macOS application menu.
     *
     * @var list<int>
     */
    public array $natives = [];

    /** @var list<string> a choice's values, as its natives are ordered */
    public array $options = [];

    /** @var list<string> a menu's nodes, in order */
    public array $children = [];

    public ?bool $on;

    public ?string $chosen;

    public ?Shortcut $shortcut;

    /** Shown, and never enabled: a kind Raven has no call for where it was put. */
    public bool $inert = false;

    /** macOS: a Quit answered through Raven's own Quit. */
    public bool $quits = false;

    public function __construct(BarNode $node, public ?string $parent)
    {
        $this->kind = $node->kind;
        $this->standard = $node->standard;
        $this->replaced = $node->replaced;
        $this->application = $node->application;
        $this->panel = $node->panel;
        $this->on = $node->on;
        $this->chosen = $node->chosen;
        $this->shortcut = $node->shortcut;
    }
}
