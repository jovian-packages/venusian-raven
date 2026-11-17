<?php

namespace Jovian\Toolkits\Raven\Primitives;

use Jovian\Toolkits\Raven\Contracts\Primitives\RavenNative;
use Jovian\Toolkits\Raven\Primitives\Concerns\RavenPrimitive;
use RPainter;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\Toolkits\Primitives\Placement;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Primitives\TKCanvas;
use Surface\HumanInput\InputChannel;
use Surface\Toolkits\Primitives\TKPrimitiveGroup;
use WeakReference;

/**
 * A custom Raven widget that paints a texture made from the pixels it was last given, stretched
 * over the widget. A frame of the same size as the last updates the texture; another size makes a
 * new one. It has no natural size: the layout sizes it.
 *
 * It is not a Raven image: an image asks for its texture's size, so on a display of twice the
 * density each frame would ask for a widget twice as tall, and the canvas would grow without end.
 *
 * Once its input is open (input()), it takes the keyboard focus: a click or focus() gives it the
 * keys. Until then Raven's Tab and clicks pass it by.
 *
 * It shows pixels and nothing more. Raven draws its widgets on its own renderer and lends no
 * surface (Metal layer, GL context or Vulkan surface) to a graphics engine.
 */
class RavenCanvas extends TKCanvas implements RavenNative
{
    use RavenPrimitive {
        destroyNative as private destroyPrimitive;
    }

    protected int $texture = 0;

    /** @var array{int, int} the size of $texture */
    protected array $textureSize = [0, 0];

    public function __construct(string $name, ToolkitWindow $window, ?TKPrimitiveGroup $parent, Placement $placement)
    {
        parent::__construct($name, $window, $parent, $placement);
        $custom = $this->adoptNative(Rwidget_create(R_WIDGET_CUSTOM));
        $self = WeakReference::create($this);
        Rcustom_set_paint_callback($custom, static function (int $widget, RPainter $painter, float $width, float $height) use ($self): void {
            $texture = $self->get()?->texture ?? 0;
            if ($texture !== 0) {
                Rdraw_image($painter, $texture, 0.0, 0.0, $width, $height, 1.0, 1.0, 1.0, 1.0);
            }
        }, null);
    }

    /**
     * @throws HumanInputException When human input is off (config/human-input.php enabled).
     */
    public function input(): InputChannel
    {
        $input = parent::input();
        Rwidget_set_focusable($this->control, R_TRUE);

        return $input;
    }

    /** The Raven window this canvas is in. */
    public function windowRid(): int
    {
        return $this->ravenWindow()->rid();
    }

    /** The window's scale: 2.0 on a display of twice the density. */
    protected function nativeScale(): float
    {
        return Rwindow_get_scale($this->ravenWindow()->rid());
    }

    /** @throws ToolkitRuntimeException When Raven makes no texture from the pixels. */
    protected function applyPixels(string $rgba8, int $width, int $height): void
    {
        if ($this->texture !== 0 && $this->textureSize === [$width, $height]) {
            Rtexture_update($this->texture, $rgba8, $width, $height);
            Rwidget_invalidate($this->control);

            return;
        }
        $texture = Rtexture_create_from_pixels($rgba8, $width, $height);
        if (Rtexture_is_valid($texture) !== R_TRUE) {
            throw new ToolkitRuntimeException("Canvas '{$this->path()}' cannot show {$width}x{$height} pixels: Raven made no texture from them.");
        }
        [$old, $this->texture, $this->textureSize] = [$this->texture, $texture, [$width, $height]];
        Rwidget_invalidate($this->control);
        if ($old !== 0) {
            Rtexture_destroy($old);
        }
    }

    protected function destroyNative(): void
    {
        $this->destroyPrimitive();
        if ($this->texture !== 0) {
            Rtexture_destroy($this->texture);
            $this->texture = 0;
        }
    }
}
