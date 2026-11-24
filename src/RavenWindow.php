<?php

namespace Jovian\Toolkits\Raven;

use Closure;
use REvent;
use RuntimeException;
use Surface\Contracts\Toolkits\Mail\Windows\WindowFocused;
use Surface\Contracts\Toolkits\Mail\Windows\WindowScaleChanged;
use Surface\Toolkits\ToolkitWindow;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use WeakReference;

/**
 * A Raven window (Rwindow_create, hidden until present()). Its content widget is a vertical box
 * the content container goes in. A child is a window its parent owns: it stays above the parent,
 * goes with it, and while it is open the parent takes no input (Raven has no modal window; the
 * parent's event hook keeps its input, and a press on it brings the child forward).
 */
class RavenWindow extends ToolkitWindow
{
    /** The scale last posted (pixels per logical unit), or read at the first show. */
    private float $scale = 1.0;

    /** Before the first show the window is on no screen of its own: a scale change then is not the user's. */
    private bool $shown_once = false;

    /** Whether the window was the active one at the last settle. */
    private bool $active = false;

    /**
     * The Raven window while open; null once closed. Raven destroys it after the close callback
     * lets it go, and destroys the windows it owns with it.
     */
    protected ?int $window = null;

    /**
     * Vertical box filling the window: where the content container goes.
     */
    protected int $content = 0;

    /**
     * Open children, held weakly: the driver holds the windows.
     *
     * @var array<int, WeakReference<RavenWindow>>
     */
    protected array $children = [];

    /**
     * Who sees this window's input before its widgets, by key: Raven has one hook per window.
     *
     * @var array<string, Closure(REvent): bool>
     */
    private array $input_watchers = [];

    public function __construct(
        string $name,
        RavenSession $session,
        RavenAdapterDriver $driver,
        int $width,
        int $height,
        ?RavenWindow $parent = null,
    ) {
        parent::__construct($name, $session, $driver, $width, $height, $parent);
    }

    /** A child presents its parent first when the parent is not shown. */
    public function present(): static
    {
        $window = $this->live();
        if ($this->parent instanceof self && ! $this->parent->shown_once) {
            $this->parent->present();
        }

        // Shows the window in front with the keyboard and makes the application the active one.
        Rwindow_focus($window);

        if (! $this->shown_once) {
            $this->shown_once = true;
            $this->scale = Rwindow_get_scale($window);
        }

        $this->focusInitial();

        return $this;
    }

    public function setTitle(string $title): static
    {
        Rwindow_set_title($this->live(), $title);

        return $this;
    }

    /**
     * The Raven window's Rid, for engines that draw into it.
     *
     * @throws RuntimeException Once closed.
     */
    public function native(): int
    {
        return $this->live();
    }

    public function isOpen(): bool
    {
        return ! is_null($this->window);
    }

    /**
     * The box the content container goes in.
     *
     * @throws RuntimeException Once closed.
     */
    public function contentArea(): int
    {
        $this->live();

        return $this->content;
    }

    /**
     * The client area in logical units: what the content container is laid out in.
     *
     * @return array{int, int}
     * @throws RuntimeException Once closed.
     */
    public function size(): array
    {
        Rwindow_get_size($this->live(), $width, $height);

        return [(int) $width, (int) $height];
    }

    /**
     * See this window's input before its widgets, while nothing blocks it: $watcher answers
     * true to keep an event from them. It should hold its primitive weakly.
     *
     * @internal For this adapter's primitives.
     * @param Closure(REvent): bool $watcher
     */
    public function watchInput(string $key, Closure $watcher): void
    {
        $this->input_watchers[$key] = $watcher;
    }

    /**
     * @internal For this adapter's primitives.
     */
    public function unwatchInput(string $key): void
    {
        unset($this->input_watchers[$key]);
    }

    /**
     * Whether this window is the one the user is working in.
     */
    public function isActive(): bool
    {
        return ! is_null($this->window) && Rwindow_is_active($this->window) === R_TRUE;
    }

    /**
     * End of a pump: Raven reports neither activation nor a scale change, so both are compared.
     * Activation posts WindowFocused once per activation, from the first show on.
     */
    public function settle(): void
    {
        if (is_null($this->window) || ! $this->shown_once) {
            return;
        }

        $active = $this->isActive();
        if ($active && ! $this->active) {
            // Raven shows the active window's bar itself; this tells topside whose bar answers.
            $this->gainedFocus();
            $this->session->post(new WindowFocused($this->name));
        }
        if (! $active && $this->active) {
            $this->lostFocus();
        }
        $this->active = $active;

        $this->scaleChanged();
    }

    /** WindowScaleChanged when the scale differs from the last posted. */
    public function scaleChanged(): void
    {
        if (is_null($this->window)) {
            return;
        }

        $scale = Rwindow_get_scale($this->window);
        if ($scale === $this->scale) {
            return;
        }
        $this->scale = $scale;
        if ($this->shown_once) {
            $this->session->post(new WindowScaleChanged($this->name, $scale));
        }
    }

    protected function bootstrapWindow(int $width, int $height): void
    {
        $window = Rwindow_create($this->name, $width, $height, R_WINDOW_HIDDEN);
        if (Rwindow_is_valid($window) !== R_TRUE) {
            Rerror_get_last($detail, $severity);
            throw new RuntimeException("Raven did not open window '{$this->name}': ".($detail ?: 'Rwindow_create failed').'.');
        }
        $this->window = $window;

        // The window takes ownership of its content: destroyed with it.
        $this->content = Rwidget_create(R_WIDGET_VBOX);
        Rwindow_set_content($window, $this->content);

        if ($this->parent instanceof self) {
            Rwindow_set_owner($window, $this->parent->native());
            $this->parent->adoptChild($this);
        }
        Rwindow_center($window);

        // The callbacks hold this window weakly: a strong capture would be a cycle through
        // ext-raven's held slots that PHP's collector cannot see, keeping every closed window alive.
        $self = WeakReference::create($this);

        // R_TRUE lets Raven destroy the window; the mail goes first. While the window confirms
        // close, the user's request posts WindowCloseRequested and R_FALSE keeps the window.
        Rwindow_set_close_callback($window, static fn (): int => ($self->get()?->closeRequested() ?? true) ? R_TRUE : R_FALSE, null);

        // A burst of resizes within one pump is one WindowResized, with the last size.
        Rwindow_set_resize_callback($window, static function (int $window, int $width, int $height) use ($self): void {
            $self->get()?->resized($width, $height);
        }, null);

        // Raven has one hook per window: a child's hold on the parent's input, then the watchers.
        // R_TRUE keeps the event from the widgets.
        Rwindow_set_event_hook($window, static fn (int $window, REvent $event): int => ($self->get()?->takesEvent($event) ?? false) ? R_TRUE : R_FALSE, null);

        $this->scale = Rwindow_get_scale($window);

        // Not ported yet (deprecated2/RavenWindow): the right-click and focus
        // tracking, and the content floor (holdContent).
    }

    /**
     * The menu bar goes first in the window's box: Raven gives the first MenuBar of a window to
     * the macOS system bar, and draws it above the content elsewhere.
     */
    public function attachMenuBar(int $bar): void
    {
        Rwidget_insert_child($this->content, $bar, 0);
    }

    /**
     * The content container fills the window's box: its slot takes all the box's height
     * (fill 1) and, as every slot starts, its whole width.
     */
    protected function mountContent(ColumnContract $content): void
    {
        $native = $content->native();
        Rwidget_add_child($this->content, $native);
        Rslot_set_fill($native, 1.0);
    }

    /**
     * Out of the window's box; the container destroys its native right after. Nothing to take
     * out once Raven destroyed the window and its widgets.
     */
    protected function unmountContent(ColumnContract $content): void
    {
        $native = $content->native();
        if (Rwidget_is_valid($this->content) === R_TRUE && Rwidget_is_valid($native) === R_TRUE && Rwidget_get_parent($native) === $this->content) {
            Rwidget_remove_child($this->content, $native);
        }
    }

    /**
     * The window's close callback: true lets Raven destroy the window, false keeps it.
     */
    protected function closeRequested(): bool
    {
        if (! $this->allowsUserClose()) {
            return false;
        }
        $this->closed();

        return true;
    }

    /**
     * Raven's close runs the close callback, which takes the close path.
     */
    protected function closeNative(): void
    {
        Rwindow_close($this->live());
    }

    protected function resized(int $width, int $height): void
    {
        parent::resized($width, $height);
        $this->scaleChanged();
    }

    protected function takesEvent(REvent $event): bool
    {
        if ($this->blocksInput($event)) {
            return true;
        }
        foreach ($this->input_watchers as $watcher) {
            if ($watcher($event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An input event of this window while a child is open: kept from the widgets, and a press
     * brings the child forward.
     */
    protected function blocksInput(REvent $event): bool
    {
        $child = $this->openChild();
        if (is_null($child)) {
            return false;
        }
        if ($event->type === R_EVENT_MOUSE_DOWN) {
            Rwindow_focus($child->native());
        }

        return true;
    }

    protected function openChild(): ?self
    {
        foreach ($this->children as $id => $child) {
            $window = $child->get();
            if (! is_null($window) && ! is_null($window->window)) {
                return $window;
            }
            unset($this->children[$id]);
        }

        return null;
    }

    protected function adoptChild(self $child): void
    {
        $this->children[spl_object_id($child)] = WeakReference::create($child);
    }

    protected function releaseChild(self $child): void
    {
        unset($this->children[spl_object_id($child)]);
    }

    protected function live(): int
    {
        return $this->window ?? throw new RuntimeException("Raven Window '{$this->name}' is closed.");
    }

    /**
     * Raven destroys the windows a window owns with it, without asking them: the children
     * take their close path first.
     */
    protected function beforeClose(): void
    {
        foreach ($this->children as $child) {
            $child->get()?->closed();
        }
        $this->children = [];
    }

    protected function releaseNative(): void
    {
        if ($this->parent instanceof self) {
            $this->parent->releaseChild($this);
        }
        $this->window = null;
        $this->active = false;
        $this->input_watchers = [];
    }
}
