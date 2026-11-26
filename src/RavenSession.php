<?php

namespace Jovian\Toolkits\Raven;

use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Surface\Contracts\Toolkits\Styling\ColorScheme;
use Surface\Toolkits\ToolkitSession;
use Surface\Toolkits\Menus\AboutPanel;
use Jovian\Toolkits\Raven\Menus\RavenMenuBar;
use Voyager\Contracts\Core\FrameworkCore;


class RavenSession extends ToolkitSession
{
    /** The About dialog's content while it is open. */
    protected ?int $about = null;
    /**
     * @param string $backend 'vulkan' or 'null'.
     * @param float $slice Longest single wait of a sliced pump, in seconds.
     */
    public function __construct(
        FrameworkCore $app,
        protected readonly string $application_name,
        protected readonly string $backend,
        protected readonly bool $headless,
        protected readonly float $ui_scale,
        protected readonly ?string $theme,
        protected readonly float $slice,
    ) {
        parent::__construct($app);
    }

    protected function bootstrapToolkitLibrary(): void
    {
        $this->application = new Raven(
            $this->application_name,
            $this->backend,
            $this->headless,
            $this->ui_scale,
            $this->theme,
            $this->slice,
        // Raven's own Quit: the shown bar's Quit answers it, or the application is asked.
        )->onQuit(fn () => RavenMenuBar::quitRequested() || $this->post(new QuitRequested()));
    }

    /**
     * Dark or light is a member of the configured theme's family, laid over it; the system's
     * choice keeps the theme as configured.
     */
    protected function applyColorScheme(ColorScheme $scheme): void
    {
        $this->application->applyColorScheme($scheme);
    }

    /**
     * Raven has no About panel of its own: a dialog over $window with the application's fields.
     * One at a time.
     */
    public function showAbout(AboutPanel $panel, int $window): void
    {
        if (! is_null($this->aboutDialog())) {
            return;
        }

        $content = Rwidget_create(R_WIDGET_VBOX);
        Rpanel_set_spacing($content, 6);
        Rwidget_set_min_size($content, 260, 0);
        $title = Rwidget_add_child($content, Rlabel_create(is_null($panel->version) ? $panel->name : "{$panel->name} {$panel->version}"));
        Rlabel_set_font($title, Rfont_get_variant(Rfont_get_default(), R_FONT_BOLD));
        Rlabel_set_text_size($title, 16);
        Rslot_set_halign($title, R_HALIGN_CENTER);
        foreach (array_filter([$panel->copyright, $panel->credits]) as $text) {
            $line = Rwidget_add_child($content, Rlabel_create($text));
            Rlabel_set_wrap($line, R_TRUE);
            Rslot_set_halign($line, R_HALIGN_CENTER);
        }
        $ok = Rwidget_add_child($content, Rbutton_create('OK'));
        Rbutton_set_style($ok, R_BUTTON_PRIMARY);
        Rslot_set_halign($ok, R_HALIGN_RIGHT);
        Rbutton_set_clicked_callback($ok, static fn (int $button) => Rdialog_close($button, 1), null);

        $this->about = $content;
        Rdialog_open($window, $content, function (): void {
            $this->about = null;
        }, null);
    }

    /** The About dialog's content while it is open. */
    public function aboutDialog(): ?int
    {
        return ! is_null($this->about) && Rwidget_is_valid($this->about) === R_TRUE ? $this->about : null;
    }

    public function application(): Raven
    {
        return $this->application;
    }
}
