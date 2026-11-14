<?php

namespace Jovian\Toolkits\Raven\Primitives\Concerns;

use Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException;
use Surface\Contracts\Toolkits\Styling\FontSpec;

/**
 * FontSpec to a Raven font. Raven embeds Roboto and Roboto Mono and loads other fonts from files
 * only: a family is found by its file in the system's font folders.
 */
final class RavenFonts
{
    /** @var array<string, int> family => font Rid */
    private static array $families = [];

    public static function font(FontSpec $font): int
    {
        $base = match (true) {
            ! is_null($font->family) => self::family($font->family),
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
        if ($style === R_FONT_REGULAR) {
            return $base;
        }
        $variant = Rfont_get_variant($base, $style);

        return $variant === R_INVALID_ID || $variant === 0 ? $base : $variant;
    }

    /**
     * @throws RavenIncompatibilityException When no font file of the family is installed.
     */
    private static function family(string $family): int
    {
        if (isset(self::$families[$family])) {
            return self::$families[$family];
        }
        $wanted = self::key($family);
        foreach (self::folders() as $folder) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! preg_match('/\.(ttf|otf)$/i', $file->getFilename())) {
                    continue;
                }
                $stem = self::key(pathinfo($file->getFilename(), PATHINFO_FILENAME));
                if ($stem === $wanted || $stem === "{$wanted}regular") {
                    $rid = Rfont_create_from_file($file->getPathname());
                    if ($rid !== R_INVALID_ID && $rid !== 0) {
                        return self::$families[$family] = $rid;
                    }
                }
            }
        }

        throw RavenIncompatibilityException::feature("The font family '{$family}'", 'Raven loads fonts from files and no .ttf or .otf file of that name is in the system font folders.');
    }

    private static function key(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
    }

    /** @return list<string> */
    private static function folders(): array
    {
        $home = getenv('HOME') ?: '';
        $folders = PHP_OS_FAMILY === 'Darwin'
            ? ['/System/Library/Fonts', '/Library/Fonts', "{$home}/Library/Fonts"]
            : ['/usr/share/fonts', '/usr/local/share/fonts', "{$home}/.local/share/fonts", "{$home}/.fonts"];

        return array_values(array_filter($folders, 'is_dir'));
    }
}
