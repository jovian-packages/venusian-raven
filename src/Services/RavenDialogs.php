<?php

namespace Jovian\Toolkits\Raven\Services;

use Jovian\Toolkits\Raven\RavenBridge;
use Jovian\Toolkits\Raven\RavenSession;
use Jovian\Toolkits\Raven\RavenWindow;
use RFileDialogDesc;
use RFileFilter;
use Surface\Contracts\Toolkits\Services\Alert;
use Surface\Contracts\Toolkits\Services\DialogCapability;
use Surface\Contracts\Toolkits\Services\OpenRequest;
use Surface\Contracts\Toolkits\Services\SaveRequest;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Services\ToolkitDialogs;
use Voyager\Contracts\Signals\NamedSignal;
use WeakReference;

/**
 * Alerts are Raven dialogs inside the parent window (Rdialog_open): title, message, detail,
 * a suppression checkbox and the buttons, answered without blocking. Raven has no alert
 * icons, so an alert's style is not shown. File requests are the
 * platform's panels through Rfile_dialog_*, which Raven runs modally: they run from a task
 * posted to the next frame, so the request returns its id first.
 */
final class RavenDialogs extends ToolkitDialogs
{
    /** @var array<string, int> request id => the dialog content's Rid */
    private array $alerts = [];

    public function __construct(RavenBridge $driver, private readonly RavenSession $session)
    {
        parent::__construct($driver);
    }

    public function supports(DialogCapability $capability): bool
    {
        return true;
    }

    protected function showAlert(string $id, ToolkitWindow $parent, Alert $alert): void
    {
        $window = self::raven($parent);
        $content = Rwidget_create(R_WIDGET_VBOX);
        Rpanel_set_spacing($content, 10);
        Rwidget_set_min_size($content, 320, 0);

        if (! is_null($alert->title)) {
            $title = Rwidget_add_child($content, Rlabel_create($alert->title));
            Rlabel_set_font($title, Rfont_get_variant(Rfont_get_default(), R_FONT_BOLD));
            Rlabel_set_text_size($title, 15);
        }
        $message = Rwidget_add_child($content, Rlabel_create($alert->message));
        Rlabel_set_wrap($message, R_TRUE);
        if (! is_null($alert->detail)) {
            $detail = Rwidget_add_child($content, Rlabel_create($alert->detail));
            Rlabel_set_wrap($detail, R_TRUE);
        }
        $check = 0;
        if (! is_null($alert->suppression)) {
            $check = Rwidget_add_child($content, Rcheckbox_create($alert->suppression));
        }

        $row = Rwidget_add_child($content, Rwidget_create(R_WIDGET_HBOX));
        Rpanel_set_spacing($row, 8);
        Rslot_set_halign($row, R_HALIGN_RIGHT);
        $weak = WeakReference::create($this);
        foreach ($alert->buttons as $index => $label) {
            $button = Rwidget_add_child($row, Rbutton_create($label));
            if ($index === $alert->default) {
                Rbutton_set_style($button, R_BUTTON_PRIMARY);
            }
            Rbutton_set_clicked_callback($button, static fn (int $button) => Rdialog_close($button, $index + 1), null);
        }

        $cancel = $alert->cancel;
        $this->alerts[$id] = $content;
        // Result 0 is Escape (or the window closing the dialog): the cancel button's answer.
        Rdialog_open($window->rid(), $content, static function (int $content, int $result) use ($weak, $id, $check, $cancel): void {
            $self = $weak->get();
            if (is_null($self)) {
                return;
            }
            $suppressed = $check !== 0 && Rcheckbox_is_checked($check) === R_TRUE;
            unset($self->alerts[$id]);
            $self->answerAlert($id, $result <= 0 ? $cancel : $result - 1, $suppressed);
        }, null);
    }

    protected function showOpen(string $id, ToolkitWindow $parent, OpenRequest $request): void
    {
        $flags = ($request->multiple ? R_FILE_DIALOG_MULTIPLE : 0) | ($request->showHidden ? R_FILE_DIALOG_SHOW_HIDDEN : 0);
        $desc = $this->desc($parent, $request->title, $request->directory, null, $request->filters, $flags);
        $this->later($id, static fn (): ?string => $request->folders ? Rfile_dialog_select_folder($desc) : Rfile_dialog_open($desc));
    }

    protected function showSave(string $id, ToolkitWindow $parent, SaveRequest $request): void
    {
        $desc = $this->desc($parent, $request->title, $request->directory, $request->name, $request->filters, $request->showHidden ? R_FILE_DIALOG_SHOW_HIDDEN : 0);
        $this->later($id, static fn (): ?string => Rfile_dialog_save($desc));
    }

    protected function dismiss(string $id): void
    {
        if (isset($this->alerts[$id])) {
            $content = $this->alerts[$id];
            unset($this->alerts[$id]);
            if (Rwidget_is_valid($content) === R_TRUE) {
                Rdialog_close($content, -1);
            }
        }
    }

    protected function post(NamedSignal $mail): void
    {
        $this->session->post($mail);
    }

    /** Run the modal panel from the next frame and answer what it returned. */
    private function later(string $id, \Closure $panel): void
    {
        $weak = WeakReference::create($this);
        Rtask_post(static function () use ($weak, $id, $panel): void {
            $first = $panel();
            $paths = [];
            if (! is_null($first)) {
                $count = Rfile_dialog_get_result_count();
                for ($i = 0; $i < $count; $i++) {
                    $paths[] = (string) Rfile_dialog_get_result($i);
                }
            }
            $weak->get()?->answerFiles($id, $paths);
        }, null);
    }

    /** @param array<string, list<string>> $filters */
    private function desc(ToolkitWindow $parent, ?string $title, ?string $directory, ?string $name, array $filters, int $flags): RFileDialogDesc
    {
        $list = [];
        foreach ($filters as $label => $patterns) {
            $list[] = new RFileFilter($label, implode(';', $patterns));
        }

        return new RFileDialogDesc(
            parent: self::raven($parent)->rid(),
            title: $title,
            directory: self::folder($directory),
            file_name: $name,
            filters: $list === [] ? null : $list,
            filter_count: count($list),
            flags: $flags,
        );
    }

    private static function raven(ToolkitWindow $window): RavenWindow
    {
        return $window instanceof RavenWindow ? $window : throw new ToolkitRuntimeException("Window '{$window->name()}' is not a raven window.");
    }
}
