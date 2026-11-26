<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Styling\ColorScheme;
use Surface\Contracts\Toolkits\UI\ButtonStyle;

/*
| Raven's null renderer: no OS window. Uses the helpers of MenuBarTest.php (one core, one
| connected Raven for the process).
*/

/** What a button's icon box holds, as Raven has it: the widgets inside, or null without an icon. */
function iconBoxChildren(object $button): ?int
{
    $control = (fn () => $this->control)->call($button);
    $content = Rwidget_get_child($control, 0);

    return Rwidget_get_child_count($content) === 0 || Rwidget_get_type($content) !== R_WIDGET_HBOX ? null : Rwidget_get_child_count($content);
}

it('holds the icon alone in a button whose label is empty, and the label beside it when there is one', function (): void {
    $menus = ravenMenus();
    $this->opened[] = $window = ravenWindow($menus);
    $button = $window->column('m')->button('b', '')->setSymbol('search');
    Rdraw_frame();

    expect(iconBoxChildren($button))->toBe(1);

    $button->setLabel('Search');
    Rdraw_frame();
    expect(iconBoxChildren($button))->toBe(2);

    $button->setLabel('');
    Rdraw_frame();
    expect(iconBoxChildren($button))->toBe(1);
});

it('draws a borderless button in solid material, so a glass theme paints no glass on hover', function (): void {
    $menus = ravenMenus();
    Rtheme_use('GlassDark');
    $this->opened[] = $window = ravenWindow($menus);
    $column = $window->column('m');
    $borderless = $column->button('a', 'A')->setStyle(ButtonStyle::BORDERLESS);
    $plain = $column->button('b', 'B');
    Rdraw_frame();

    $material = fn (object $button): int => Rwidget_get_material((fn () => $this->control)->call($button));

    expect($material($borderless))->toBe(R_MATERIAL_SOLID)
        ->and($material($plain))->not->toBe(R_MATERIAL_SOLID);

    Rtheme_use('Dark');
});

it('draws the dark or light member of the theme in use, and the system scheme changes nothing', function (): void {
    $raven = ravenMenus()[2]->application();

    Rtheme_use('GlassDark');
    $raven->applyColorScheme(ColorScheme::SYSTEM);
    expect(Rtheme_get_name())->toBe('GlassDark');

    $raven->applyColorScheme(ColorScheme::LIGHT);
    expect(Rtheme_is_dark())->toBe(R_FALSE)
        ->and(Rtheme_get_name())->toBe('GlassLight');

    $raven->applyColorScheme(ColorScheme::LIGHT);
    expect(Rtheme_get_name())->toBe('GlassLight');

    $raven->applyColorScheme(ColorScheme::DARK);
    expect(Rtheme_is_dark())->toBe(R_TRUE)
        ->and(Rtheme_get_name())->toBe('GlassDark');

    Rtheme_use('HighContrastLight');
    $raven->applyColorScheme(ColorScheme::DARK);
    expect(Rtheme_get_name())->toBe('HighContrastDark');

    Rtheme_use('Dark');
});
