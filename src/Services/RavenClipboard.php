<?php

namespace Jovian\Toolkits\Raven\Services;

use Jovian\Toolkits\Raven\RavenSession;
use Surface\Contracts\Toolkits\Mail\Services\ClipboardChanged;
use Surface\Toolkits\Services\ToolkitClipboard;
use Voyager\Contracts\Signals\NamedSignal;
use WeakReference;

/**
 * Raven's text clipboard. Raven reports no clipboard change, so the text is compared after
 * every pump: a difference posts ClipboardChanged (latest wins).
 */
final class RavenClipboard extends ToolkitClipboard
{
    private string $seen;

    public function __construct(private readonly RavenSession $session)
    {
        $this->seen = (string) Rclipboard_get_text();
        $weak = WeakReference::create($this);
        $session->afterPump('clipboard', static fn () => $weak->get()?->compare());
    }

    public function setText(string $text): void
    {
        Rclipboard_set_text($text);
    }

    public function hasText(): bool
    {
        return (string) Rclipboard_get_text() !== '';
    }

    public function compare(): void
    {
        $text = (string) Rclipboard_get_text();
        if ($text !== $this->seen) {
            $this->seen = $text;
            $this->session->postLatest('clipboard.changed', new ClipboardChanged());
        }
    }

    protected function readText(string $id): void
    {
        $text = (string) Rclipboard_get_text();
        $this->answerText($id, $text === '' ? null : $text);
    }

    protected function post(NamedSignal $mail): void
    {
        $this->session->post($mail);
    }
}
