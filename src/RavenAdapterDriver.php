<?php

namespace Jovian\Toolkits\Raven;

use ReflectionException;
use RuntimeException;
use Voyager\Contracts\Core\FrameworkCore;
use Surface\Toolkits\Drivers\ToolkitAdapterDriver;
use Jovian\Toolkits\Raven\Menus\RavenMenuBar;
use Surface\Contracts\Toolkits\UI\TKRow;
use Surface\Contracts\Toolkits\UI\TKFixed;
use Surface\Contracts\Toolkits\UI\TKLabel;
use Surface\Contracts\Toolkits\UI\TKButton;
use Surface\Contracts\Toolkits\UI\TKColumn;
use Surface\Contracts\Toolkits\UI\TKToggle;
use Surface\Contracts\Toolkits\UI\TKCheckbox;
use Surface\Contracts\Toolkits\UI\TKDropdown;
use Surface\Contracts\Toolkits\UI\TKTextInput;
use Surface\Contracts\Toolkits\UI\TKLink;
use Surface\Contracts\Toolkits\UI\TKComboBox;
use Surface\Contracts\Toolkits\UI\TKDatepicker;
use Jovian\Toolkits\Raven\UI\RavenTextInput;
use Jovian\Toolkits\Raven\UI\RavenLink;
use Jovian\Toolkits\Raven\UI\RavenComboBox;
use Jovian\Toolkits\Raven\UI\RavenDatepicker;
use Jovian\Toolkits\Raven\UI\RavenRow;
use Jovian\Toolkits\Raven\UI\RavenFixed;
use Jovian\Toolkits\Raven\UI\RavenLabel;
use Jovian\Toolkits\Raven\UI\RavenButton;
use Jovian\Toolkits\Raven\UI\RavenColumn;
use Jovian\Toolkits\Raven\UI\RavenToggle;
use Jovian\Toolkits\Raven\UI\RavenCheckbox;
use Jovian\Toolkits\Raven\UI\RavenDropdown;
use Surface\Contracts\Toolkits\UI\TKTable;
use Jovian\Toolkits\Raven\UI\RavenTable;
use Surface\Contracts\Toolkits\UI\TKListView;
use Jovian\Toolkits\Raven\UI\RavenListView;
use Surface\Contracts\Toolkits\UI\TKCollectionView;
use Jovian\Toolkits\Raven\UI\RavenCollectionView;
use Surface\Contracts\Toolkits\UI\TKTree;
use Jovian\Toolkits\Raven\UI\RavenTree;
use Surface\Contracts\Toolkits\UI\TKTextArea;
use Jovian\Toolkits\Raven\UI\RavenTextArea;
use Surface\Contracts\Toolkits\UI\TKScrollView;
use Jovian\Toolkits\Raven\UI\RavenScrollView;
use Surface\Contracts\Toolkits\UI\TKTabView;
use Jovian\Toolkits\Raven\UI\RavenTabView;
use Surface\Contracts\Toolkits\UI\TKSplitView;
use Jovian\Toolkits\Raven\UI\RavenSplitView;
use Surface\Contracts\Toolkits\UI\TKGrid;
use Jovian\Toolkits\Raven\UI\RavenGrid;
use Surface\Contracts\Toolkits\UI\TKOverlay;
use Jovian\Toolkits\Raven\UI\RavenOverlay;
use Surface\Contracts\Toolkits\UI\TKSeparator;
use Jovian\Toolkits\Raven\UI\RavenSeparator;
use Surface\Contracts\Toolkits\UI\TKProgressBar;
use Jovian\Toolkits\Raven\UI\RavenProgressBar;
use Surface\Contracts\Toolkits\UI\TKSpinner;
use Jovian\Toolkits\Raven\UI\RavenSpinner;
use Surface\Contracts\Toolkits\UI\TKSlider;
use Jovian\Toolkits\Raven\UI\RavenSlider;
use Surface\Contracts\Toolkits\UI\TKToggleButton;
use Jovian\Toolkits\Raven\UI\RavenToggleButton;
use Surface\Contracts\Toolkits\UI\TKSegmentedControl;
use Jovian\Toolkits\Raven\UI\RavenSegmentedControl;
use Surface\Contracts\Toolkits\UI\TKImage;
use Jovian\Toolkits\Raven\UI\RavenImage;

class RavenAdapterDriver extends ToolkitAdapterDriver
{
    protected string $window = RavenWindow::class;

    protected ?string $menu_bar = RavenMenuBar::class;

    protected array $primitives = [
        TKColumn::class => RavenColumn::class,
        TKRow::class => RavenRow::class,
        TKFixed::class => RavenFixed::class,
        TKLabel::class => RavenLabel::class,
        TKButton::class => RavenButton::class,
        TKCheckbox::class => RavenCheckbox::class,
        TKToggle::class => RavenToggle::class,
        TKDropdown::class => RavenDropdown::class,
        TKTextInput::class => RavenTextInput::class,
        TKLink::class => RavenLink::class,
        TKComboBox::class => RavenComboBox::class,
        TKDatepicker::class => RavenDatepicker::class,
        TKSeparator::class => RavenSeparator::class,
        TKProgressBar::class => RavenProgressBar::class,
        TKSpinner::class => RavenSpinner::class,
        TKSlider::class => RavenSlider::class,
        TKToggleButton::class => RavenToggleButton::class,
        TKSegmentedControl::class => RavenSegmentedControl::class,
        TKImage::class => RavenImage::class,
        TKScrollView::class => RavenScrollView::class,
        TKTabView::class => RavenTabView::class,
        TKSplitView::class => RavenSplitView::class,
        TKGrid::class => RavenGrid::class,
        TKOverlay::class => RavenOverlay::class,
        TKTable::class => RavenTable::class,
        TKListView::class => RavenListView::class,
        TKCollectionView::class => RavenCollectionView::class,
        TKTree::class => RavenTree::class,
        TKTextArea::class => RavenTextArea::class,
    ];

    public function __construct(
        FrameworkCore $app
    ) {
        if (! extension_loaded('raven')) {
            throw new RuntimeException("ext-raven missing. Install it with 'pie install php-io-extensions/raven'");
        }

        parent::__construct($app);
    }

    /**
     * @throws ReflectionException
     */
    protected function instanceSession(): void
    {
        $theme = config('raven.theme');
        $slice_ms = (float) config('raven.wait_slice_ms', 8);

        $this->session ??= new RavenSession(
            $this->app,
            (string) config('raven.application_name', config('app.name', 'Venusian')),
            (string) config('raven.backend', 'vulkan'),
            (bool) config('raven.headless', false),
            (float) config('raven.ui_scale', 0.0),
            is_string($theme) && $theme !== '' ? $theme : null,
            $slice_ms / 1000,
        );
    }
}
