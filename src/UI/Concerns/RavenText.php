<?php

namespace Jovian\Toolkits\Raven\UI\Concerns;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextAlignment;

/**
 * Fonts and alignment, for every Raven primitive that shows text.
 */
trait RavenText
{
    /** @var array<string, int|null> family => font Rid, or null when no file of it loads; once per class */
    private static array $families = [];

    protected static function ravenAlignment(TextAlignment $alignment): int
    {
        return match ($alignment) {
            TextAlignment::LEFT => R_TEXT_ALIGN_LEFT,
            TextAlignment::CENTER => R_TEXT_ALIGN_CENTER,
            TextAlignment::RIGHT => R_TEXT_ALIGN_RIGHT,
        };
    }

    /**
     * The family's font (or the default, or the monospaced) in the style nearest the weight.
     */
    protected static function ravenFont(FontSpec $font): int
    {
        $base = match (true) {
            ! is_null($font->family) => self::family($font->family) ?? Rfont_get_default(),
            $font->monospace => Rfont_get_monospace(),
            default => Rfont_get_default(),
        };
        $bold = $font->weight->toCssWeight() >= 600;
        $style = match (true) {
            $bold && $font->italic => R_FONT_BOLD_ITALIC,
            $bold => R_FONT_BOLD,
            $font->italic => R_FONT_ITALIC,
            default => R_FONT_REGULAR,
        };

        return $style === R_FONT_REGULAR ? $base : Rfont_get_variant($base, $style);
    }

    /**
     * The font loaded from the family's file: "<family>.ttf|otf" or "<family>-Regular...",
     * compared without case, spaces or punctuation. Null when no such file loads; the miss is
     * remembered, so the folders are searched once per family.
     */
    private static function family(string $family): ?int
    {
        if (array_key_exists($family, self::$families)) {
            return self::$families[$family];
        }

        $wanted = self::fontKey($family);
        foreach (self::fontFolders() as $folder) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! preg_match('/\.(ttf|otf)$/i', $file->getFilename())) {
                    continue;
                }
                $stem = self::fontKey(pathinfo($file->getFilename(), PATHINFO_FILENAME));
                if ($stem !== $wanted && $stem !== "{$wanted}regular") {
                    continue;
                }
                $font = Rfont_create_from_file($file->getPathname());
                if ($font !== R_INVALID_ID && $font !== 0) {
                    return self::$families[$family] = $font;
                }
            }
        }

        return self::$families[$family] = null;
    }

    private static function fontKey(string $name): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $name));
    }

    /**
     * @return list<string>
     */
    private static function fontFolders(): array
    {
        $home = getenv('HOME') ?: '';
        $folders = PHP_OS_FAMILY === 'Darwin'
            ? ['/System/Library/Fonts', '/Library/Fonts', "{$home}/Library/Fonts"]
            : ['/usr/share/fonts', '/usr/local/share/fonts', "{$home}/.local/share/fonts", "{$home}/.fonts"];

        return array_values(array_filter($folders, 'is_dir'));
    }
}
