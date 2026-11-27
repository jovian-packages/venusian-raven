<?php

namespace Jovian\Toolkits\Raven;

use Closure;
use REvent;
use RFileDialogDesc;
use RuntimeException;
use Surface\Contracts\Toolkits\Mail\Windows\WindowFocused;
use Surface\Contracts\Toolkits\Mail\Windows\WindowScaleChanged;
use Surface\Toolkits\ToolkitWindow;
use Surface\Contracts\Toolkits\Dialogs\Alert;
use Surface\Contracts\Toolkits\Dialogs\SaveRequest;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use WeakMap;
use WeakReference;
use Jovian\Toolkits\Raven\UI\RavenLabel;

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

    /** The content of the alert open on the window, until it is answered; 0 when none. */
    protected int $alert = 0;

    /** Whether a save panel is asked for and not shown yet: it opens in the next frame. */
    private bool $save_pending = false;

    /**
     * Who sees this window's input before its widgets, by key: Raven has one hook per window.
     *
     * @var array<string, Closure(REvent): bool>
     */
    private array $input_watchers = [];

    /** @var WeakMap<RavenLabel, true>|null the labels that truncate: cut again after each pump */
    private ?WeakMap $truncating = null;

    /** @var WeakMap<object, true>|null the views that report their size: asked after each pump */
    private ?WeakMap $sized = null;

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

        // Raven reports no resize of a widget: a truncating label is cut again to its width.
        foreach ($this->truncating ?? [] as $label => $_) {
            $label->recut();
        }

        // Nor of a view: each that watches its size says whether it changed.
        foreach ($this->sized ?? [] as $view => $_) {
            $view->checkSize();
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

    /**
     * Cut $label again to its width after each pump, while it truncates.
     *
     * @internal RavenLabel
     */
    public function watchWidth(RavenLabel $label): void
    {
        $this->truncating ??= new WeakMap();
        $this->truncating[$label] = true;
    }

    /**
     * @internal RavenLabel
     */
    public function unwatchWidth(RavenLabel $label): void
    {
        unset($this->truncating[$label]);
    }

    /**
     * Ask $view after each pump whether its size changed, while it watches its size.
     *
     * @internal RavenNative
     */
    public function watchSize(object $view): void
    {
        $this->sized ??= new WeakMap();
        $this->sized[$view] = true;
    }

    /**
     * @internal RavenNative
     */
    public function unwatchSize(object $view): void
    {
        unset($this->sized[$view]);
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

    /**
     * A Raven dialog over the window's content, laid out as Raven lays out its own message box
     * (Rmessage_box, whose buttons are fixed sets): the message at 17 points, the detail
     * wrapped under it, the buttons in a row on the right, the default one Primary. Enter
     * presses the default; Esc dismisses the dialog, which answers the cancel button. The
     * suppression box is a Raven check box above the buttons. Raven's dialogs have no style
     * and no title: those parts are left out.
     */
    protected function showAlert(Alert $alert): void
    {
        $window = $this->live();
        $box = Rwidget_create(R_WIDGET_VBOX);
        Rpanel_set_spacing($box, 12);
        Rwidget_set_size($box, 380, -1);

        $message = Rlabel_create($alert->message);
        Rlabel_set_text_size($message, 17);
        Rwidget_add_child($box, $message);
        if (! is_null($alert->detail)) {
            $detail = Rlabel_create($alert->detail);
            Rlabel_set_wrap($detail, R_TRUE);
            Rwidget_set_opacity($detail, 0.8);
            Rwidget_add_child($box, $detail);
        }
        $suppression = 0;
        if (! is_null($alert->suppression)) {
            $suppression = Rcheckbox_create($alert->suppression);
            Rwidget_add_child($box, $suppression);
        }

        // A dialog's result is the button's index plus one: Raven's 0 is the dismissed dialog.
        $row = Rwidget_create(R_WIDGET_HBOX);
        Rpanel_set_spacing($row, 8);
        foreach ($alert->buttons as $index => $label) {
            $button = Rbutton_create($label);
            if ($index === $alert->default) {
                Rbutton_set_style($button, R_BUTTON_PRIMARY);
            }
            Rwidget_set_min_size($button, 84, 0);
            Rbutton_set_clicked_callback($button, static fn (int $button) => Rdialog_close($button, $index + 1), null);
            Rwidget_add_child($row, $button);
        }
        Rwidget_add_child($box, $row);
        Rslot_set_halign($row, R_HALIGN_RIGHT);
        Rslot_set_padding($row, 0, 6, 0, 0);

        $default = $alert->default + 1;
        Rwidget_set_event_callback($box, static function (int $widget, REvent $event) use ($default): int {
            if ($event->type !== R_EVENT_KEY_DOWN || ($event->key !== R_KEY_ENTER && $event->key !== R_KEY_KP_ENTER)) {
                return R_FALSE;
            }
            Rdialog_close($widget, $default);

            return R_TRUE;
        }, null);

        $self = WeakReference::create($this);
        $cancel = $alert->cancel;
        $this->alert = $box;
        Rdialog_open($window, $box, static function (int $content, int $result) use ($self, $suppression, $cancel): void {
            $window = $self->get();
            if (is_null($window) || $window->alert !== $content) {
                return;
            }
            $window->alert = 0;
            // The content is still whole here: Raven deletes it after this callback.
            $window->alertAnswered(
                $result === 0 ? $cancel : $result - 1,
                $suppression !== 0 && Rcheckbox_is_checked($suppression) === R_TRUE,
            );
        }, null);
    }

    /**
     * Raven's own save panel (Rfile_dialog_save): the system's where Raven has one, else its
     * file browser in a dialog over the window. The call waits for the answer in a loop of its
     * own, so it runs from the next frame (Rtask_post) and this returns at once. The title,
     * folder and suggested name are the panel's; it asks itself before replacing a file. The
     * path chosen, or null for a cancel, is the answer.
     */
    protected function showSave(SaveRequest $request): void
    {
        $desc = new RFileDialogDesc(parent: $this->live(), title: $request->title, directory: $request->directory, file_name: $request->name);

        $self = WeakReference::create($this);
        $this->save_pending = true;
        Rtask_post(static function () use ($self, $desc): void {
            $window = $self->get();
            if (is_null($window) || ! $window->save_pending) {
                return;
            }
            $window->save_pending = false;
            $path = Rfile_dialog_save($desc);
            $window->saveAnswered($path);
        }, null);
    }

    /** A panel not shown yet is not shown; one that is open goes with the window. */
    protected function closeSave(): void
    {
        $this->save_pending = false;
    }

    /** Raven asks the OS to open it with the user's browser. */
    protected function openUrl(string $url): void
    {
        Ropen_url($url);
    }

    /** The dialog closes before the window goes; its callback then finds it gone. */
    protected function closeAlert(): void
    {
        $box = $this->alert;
        $this->alert = 0;
        if ($box !== 0 && Rwidget_is_valid($box) === R_TRUE) {
            Rdialog_close($box, 0);
        }
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
