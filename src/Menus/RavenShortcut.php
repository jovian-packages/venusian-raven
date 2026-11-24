<?php

namespace Jovian\Toolkits\Raven\Menus;

use Surface\Toolkits\Menus\Shortcut;
use Surface\Contracts\Toolkits\Menus\ShortcutKey;

/**
 * A Shortcut as Raven has it: a key and modifiers, and on macOS the text a key shows when it
 * is not given to the system bar.
 *
 * @internal
 */
final class RavenShortcut
{
    /** Raven's constant of each key that is not a letter or a digit. */
    private const array NAMES = [
        '-' => 'MINUS', '=' => 'EQUAL', '[' => 'LEFT_BRACKET', ']' => 'RIGHT_BRACKET', '\\' => 'BACKSLASH',
        ';' => 'SEMICOLON', "'" => 'APOSTROPHE', '`' => 'GRAVE', ',' => 'COMMA', '.' => 'PERIOD', '/' => 'SLASH',
        'PageUp' => 'PAGE_UP', 'PageDown' => 'PAGE_DOWN',
    ];

    /** How macOS writes each key. */
    private const array MAC = [
        'Up' => '↑', 'Down' => '↓', 'Left' => '←', 'Right' => '→', 'Home' => '↖', 'End' => '↘',
        'PageUp' => '⇞', 'PageDown' => '⇟', 'Space' => 'Space', 'Enter' => '↩', 'Tab' => '⇥',
        'Escape' => '⎋', 'Backspace' => '⌫', 'Delete' => '⌦',
    ];

    public static function key(ShortcutKey $key): int
    {
        $name = 'R_KEY_'.(self::NAMES[$key->value] ?? strtoupper($key->value));

        return defined($name) ? constant($name) : R_KEY_UNKNOWN;
    }

    /**
     * Primary is Command on macOS, which Raven names Super, and Control elsewhere.
     */
    public static function modifiers(Shortcut $shortcut, bool $mac): int
    {
        return ($shortcut->primary ? ($mac ? R_MOD_SUPER : R_MOD_CTRL) : 0)
            | ($shortcut->ctrl ? R_MOD_CTRL : 0)
            | ($shortcut->alt ? R_MOD_ALT : 0)
            | ($shortcut->shift ? R_MOD_SHIFT : 0);
    }

    /**
     * The shortcut as macOS writes it in a menu, or as Raven does elsewhere.
     */
    public static function text(Shortcut $shortcut, bool $mac): string
    {
        $key = $shortcut->key->value;
        if ($mac) {
            return ($shortcut->ctrl ? '⌃' : '').($shortcut->alt ? '⌥' : '').($shortcut->shift ? '⇧' : '').($shortcut->primary ? '⌘' : '')
                .(self::MAC[$key] ?? $key);
        }

        return ($shortcut->primary || $shortcut->ctrl ? 'Ctrl+' : '').($shortcut->alt ? 'Alt+' : '').($shortcut->shift ? 'Shift+' : '').$key;
    }
}
