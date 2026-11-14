<?php

namespace Jovian\Toolkits\Raven\Primitives;

use DateTimeImmutable;
use Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException;
use Jovian\Toolkits\Raven\RavenWindow;
use Surface\Contracts\Toolkits\Menus\ContextMenu;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\Primitives\PrimitiveFactory;
use Surface\Contracts\Toolkits\Primitives\TKButton;
use Surface\Contracts\Toolkits\Primitives\TKCanvas;
use Surface\Contracts\Toolkits\Primitives\TKCheckbox;
use Surface\Contracts\Toolkits\Primitives\TKCollectionView;
use Surface\Contracts\Toolkits\Primitives\TKColumn;
use Surface\Contracts\Toolkits\Primitives\TKComboBox;
use Surface\Contracts\Toolkits\Primitives\TKDatepicker;
use Surface\Contracts\Toolkits\Primitives\TKDropdown;
use Surface\Contracts\Toolkits\Primitives\TKFixed;
use Surface\Contracts\Toolkits\Primitives\TKGrid;
use Surface\Contracts\Toolkits\Primitives\TKImage;
use Surface\Contracts\Toolkits\Primitives\TKLabel;
use Surface\Contracts\Toolkits\Primitives\TKLink;
use Surface\Contracts\Toolkits\Primitives\TKListView;
use Surface\Contracts\Toolkits\Primitives\TKMenuButton;
use Surface\Contracts\Toolkits\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Primitives\TKProgressBar;
use Surface\Contracts\Toolkits\Primitives\TKRow;
use Surface\Contracts\Toolkits\Primitives\TKScrollBar;
use Surface\Contracts\Toolkits\Primitives\TKScrollView;
use Surface\Contracts\Toolkits\Primitives\TKSegmentedControl;
use Surface\Contracts\Toolkits\Primitives\TKSeparator;
use Surface\Contracts\Toolkits\Primitives\TKSlider;
use Surface\Contracts\Toolkits\Primitives\TKSpinner;
use Surface\Contracts\Toolkits\Primitives\TKSplitView;
use Surface\Contracts\Toolkits\Primitives\TKTabView;
use Surface\Contracts\Toolkits\Primitives\TKTable;
use Surface\Contracts\Toolkits\Primitives\TKTextArea;
use Surface\Contracts\Toolkits\Primitives\TKTextInput;
use Surface\Contracts\Toolkits\Primitives\TKToggle;
use Surface\Contracts\Toolkits\Primitives\TKToggleButton;
use Surface\Contracts\Toolkits\Primitives\TKTree;
use Surface\Contracts\Toolkits\Primitives\TKVideo;
use Surface\Contracts\Toolkits\Primitives\TKWebView;

/**
 * Mints raven primitives for one window. The kinds Raven has no widget for throw
 * RavenIncompatibilityException, naming what is missing.
 */
class RavenPrimitiveFactory implements PrimitiveFactory
{
    public function __construct(
        protected readonly RavenWindow $window,
    ) {}

    public function mintLabel(TKPrimitiveGroup $host, string $name, string $text): TKLabel
    {
        return new RavenLabel($name, $this->window, $host, $this->placement($host), $text);
    }

    public function mintLink(TKPrimitiveGroup $host, string $name, string $text, string $url): TKLink
    {
        return new RavenLink($name, $this->window, $host, $this->placement($host), $text, $url);
    }

    public function mintButton(TKPrimitiveGroup $host, string $name, string $label): TKButton
    {
        return new RavenButton($name, $this->window, $host, $this->placement($host), $label);
    }

    public function mintImage(TKPrimitiveGroup $host, string $name, ?string $file): TKImage
    {
        return new RavenImage($name, $this->window, $host, $this->placement($host), $file);
    }

    public function mintCanvas(TKPrimitiveGroup $host, string $name): TKCanvas
    {
        return new RavenCanvas($name, $this->window, $host, $this->placement($host));
    }

    public function mintSeparator(TKPrimitiveGroup $host, string $name, bool $horizontal): TKSeparator
    {
        return new RavenSeparator($name, $this->window, $host, $this->placement($host), $horizontal);
    }

    public function mintSpinner(TKPrimitiveGroup $host, string $name): TKSpinner
    {
        return new RavenSpinner($name, $this->window, $host, $this->placement($host));
    }

    public function mintProgressBar(TKPrimitiveGroup $host, string $name, ?float $fraction): TKProgressBar
    {
        return new RavenProgressBar($name, $this->window, $host, $this->placement($host), $fraction);
    }

    public function mintTextInput(TKPrimitiveGroup $host, string $name, string $value, ?string $placeholder, bool $secret): TKTextInput
    {
        return new RavenTextInput($name, $this->window, $host, $this->placement($host), $value, $placeholder, $secret);
    }

    public function mintTextArea(TKPrimitiveGroup $host, string $name, string $value): TKTextArea
    {
        return new RavenTextArea($name, $this->window, $host, $this->placement($host), $value);
    }

    public function mintCheckbox(TKPrimitiveGroup $host, string $name, string $label, bool $checked): TKCheckbox
    {
        return new RavenCheckbox($name, $this->window, $host, $this->placement($host), $label, $checked);
    }

    public function mintToggle(TKPrimitiveGroup $host, string $name, bool $on): TKToggle
    {
        return new RavenToggle($name, $this->window, $host, $this->placement($host), $on);
    }

    public function mintToggleButton(TKPrimitiveGroup $host, string $name, string $label, bool $pressed): TKToggleButton
    {
        return new RavenToggleButton($name, $this->window, $host, $this->placement($host), $label, $pressed);
    }

    public function mintSlider(TKPrimitiveGroup $host, string $name, float $min, float $max, float $value): TKSlider
    {
        return new RavenSlider($name, $this->window, $host, $this->placement($host), $min, $max, $value);
    }

    public function mintScrollBar(TKPrimitiveGroup $host, string $name, bool $vertical): TKScrollBar
    {
        throw RavenIncompatibilityException::feature('TKScrollBar', 'Raven has scroll boxes but no standalone scroll bar widget.');
    }

    public function mintDropdown(TKPrimitiveGroup $host, string $name, array $options, int $selected): TKDropdown
    {
        return new RavenDropdown($name, $this->window, $host, $this->placement($host), $options, $selected);
    }

    public function mintComboBox(TKPrimitiveGroup $host, string $name, string $value, array $options): TKComboBox
    {
        return new RavenComboBox($name, $this->window, $host, $this->placement($host), $value, $options);
    }

    public function mintMenuButton(TKPrimitiveGroup $host, string $name, string $label, ContextMenu $menu): TKMenuButton
    {
        return new RavenMenuButton($name, $this->window, $host, $this->placement($host), $label, $menu);
    }

    public function mintDatepicker(TKPrimitiveGroup $host, string $name, ?DateTimeImmutable $date): TKDatepicker
    {
        return new RavenDatepicker($name, $this->window, $host, $this->placement($host), $date);
    }

    public function mintTable(TKPrimitiveGroup $host, string $name, array $columns, array $rows): TKTable
    {
        return new RavenTable($name, $this->window, $host, $this->placement($host), $columns, $rows);
    }

    public function mintSegmentedControl(TKPrimitiveGroup $host, string $name, array $segments, int $selected): TKSegmentedControl
    {
        return new RavenSegmentedControl($name, $this->window, $host, $this->placement($host), $segments, $selected);
    }

    public function mintCollectionView(TKPrimitiveGroup $host, string $name, array $items): TKCollectionView
    {
        return new RavenCollectionView($name, $this->window, $host, $this->placement($host), $items);
    }

    public function mintListView(TKPrimitiveGroup $host, string $name, array $items): TKListView
    {
        return new RavenListView($name, $this->window, $host, $this->placement($host), $items);
    }

    public function mintTree(TKPrimitiveGroup $host, string $name, array $columns, array $nodes): TKTree
    {
        return new RavenTree($name, $this->window, $host, $this->placement($host), $columns, $nodes);
    }

    public function mintVideo(TKPrimitiveGroup $host, string $name, ?string $file): TKVideo
    {
        throw RavenIncompatibilityException::feature('TKVideo', 'Raven has no media player widget.');
    }

    public function mintWebView(TKPrimitiveGroup $host, string $name): TKWebView
    {
        throw RavenIncompatibilityException::feature('TKWebView', 'Raven 0.2.3 is built without its web pages (they drive a separate Chromium-based browser, not a view inside a window).');
    }

    public function mintColumn(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKColumn
    {
        return new RavenColumn($name, $this->window, $host, $this->placement($host), $spacing, $padding);
    }

    public function mintRow(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKRow
    {
        return new RavenRow($name, $this->window, $host, $this->placement($host), $spacing, $padding);
    }

    public function mintGrid(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKGrid
    {
        return new RavenGrid($name, $this->window, $host, $this->placement($host), $spacing, $padding);
    }

    public function mintFixed(?TKPrimitiveGroup $host, string $name): TKFixed
    {
        return new RavenFixed($name, $this->window, $host, $this->placement($host));
    }

    public function mintScrollView(TKPrimitiveGroup $host, string $name): TKScrollView
    {
        return new RavenScrollView($name, $this->window, $host, $this->placement($host));
    }

    public function mintSplit(TKPrimitiveGroup $host, string $name, bool $horizontal): TKSplitView
    {
        return new RavenSplitView($name, $this->window, $host, $this->placement($host), $horizontal);
    }

    public function mintTabs(TKPrimitiveGroup $host, string $name): TKTabView
    {
        return new RavenTabView($name, $this->window, $host, $this->placement($host));
    }

    /** The slot the host hands out for this mint; the window's content takes the next one. */
    protected function placement(?TKPrimitiveGroup $host): Placement
    {
        return $host?->takePlacement() ?? Placement::next();
    }
}
