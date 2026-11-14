<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use RPainter;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKComboBox;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;

/**
 * Raven's combo box takes no typing: an editable combo box is a Raven text input with a button
 * opening the options as a Raven popup menu; completions open the same menu under the field.
 */
class RavenComboBox extends TKComboBox implements RavenNative
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
    }

    protected int $input;

    protected int $menu = 0;

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement, string $value, array $options)
    {
        parent::__construct($name, $window, $parent, $placement, $value, $options);
        $row = $this->adoptNative(Rwidget_create(R_WIDGET_HBOX));
        Rpanel_set_spacing($row, 2);
        $this->input = Rwidget_add_child($row, Rwidget_create(R_WIDGET_TEXT_INPUT));
        Rslot_set_fill($this->input, 1.0);
        Rtext_input_set_text($this->input, $value);
        $button = Rwidget_add_child($row, Rbutton_create(''));
        $arrow = Rwidget_add_child($button, Rwidget_create(R_WIDGET_CUSTOM));
        Rwidget_set_min_size($arrow, 10, 10);
        Rcustom_set_paint_callback($arrow, static function (int $widget, RPainter $painter, float $width, float $height): void {
            Rtheme_get_color(R_COLOR_TEXT, $r, $g, $b, $a);
            Rdraw_triangle($painter, 1, $height * 0.3, $width - 1, $height * 0.3, $width / 2, $height * 0.75, $r, $g, $b, $a);
        }, null);

        $self = WeakReference::create($this);
        Rbutton_set_clicked_callback($button, static function () use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling() && $primitive->options !== []) {
                $primitive->popup($primitive->options, true);
            }
        }, null);
        Rtext_input_set_changed_callback($this->input, static function (int $input, ?string $text) use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->nativeEdited((string) $text);
                $primitive->session()->post(new TextChanged($primitive->window->name(), $primitive->path(), $primitive->uuid, (string) $text));
            }
        }, null);
        Rtext_input_set_committed_callback($this->input, static function () use ($self): void {
            $primitive = $self->get();
            if ($primitive?->handling()) {
                $primitive->session()->post(new TextSubmitted($primitive->window->name(), $primitive->path(), $primitive->uuid, $primitive->value));
            }
        }, null);
    }

    public function focusRid(): int
    {
        return $this->input;
    }

    /** A menu of $values under the field; a choice writes the field and posts SelectionChanged. */
    public function popup(array $values, bool $options): void
    {
        $this->closeMenu();
        $this->menu = Rwidget_create(R_WIDGET_MENU);
        $self = WeakReference::create($this);
        foreach ($values as $value) {
            $item = Rmenu_add_item($this->menu, $value);
            Rmenu_item_set_clicked_callback($item, static fn () => $self->get()?->chose($value), null);
        }
        Rwidget_get_rect($this->native, $x, $y, $width, $height);
        Rmenu_open($this->menu, $this->ravenWindow()->rid(), $x, $y + $height);
    }

    public function chose(string $value): void
    {
        if (! $this->handling()) {
            return;
        }
        $this->nativeChose($value);
        $this->applying(fn () => Rtext_input_set_text($this->input, $value));
        $index = array_search($value, $this->options, true);
        $this->session()->post(new SelectionChanged($this->window->name(), $this->path(), $this->uuid, $index === false ? -1 : $index, $value));
    }

    protected function applyValue(string $value): void
    {
        $this->closeMenu();
        $this->applying(fn () => Rtext_input_set_text($this->input, $value));
    }

    /** The options are read when the button opens them. */
    protected function applyOptions(array $options): void {}

    protected function applyCompletions(array $completions): bool
    {
        if ($completions === []) {
            $this->closeMenu();

            return true;
        }
        if (Rwidget_is_focused($this->input) !== R_TRUE) {
            return false;
        }
        $this->popup($completions, false);

        return true;
    }

    protected function closeMenu(): void
    {
        if ($this->menu !== 0 && Rwidget_is_valid($this->menu) === R_TRUE) {
            Rwidget_destroy($this->menu);
        }
        $this->menu = 0;
    }

    protected function destroyNative(): void
    {
        $this->closeMenu();
        $this->destroyPrimitive();
    }
}
