<?php

namespace Jovian\Toolkits\Raven\UI\Support;

use Surface\Toolkits\ToolkitException;

/**
 * An icon as topside gives it, a file and a symbol: the file's texture when there is one, else the
 * icon Raven ships under that name, else none. A file's texture is the caller's to destroy; one of
 * Raven's icons is Raven's.
 *
 * @internal
 */
final class RavenIcon
{
    /** @var array<string, true>|null the names of the icons Raven ships */
    private static ?array $symbols = null;

    /**
     * @return array{int, bool} the texture (R_INVALID_ID for none), and whether it is the caller's
     * @throws ToolkitException When Raven reads no picture in the file.
     */
    public static function texture(?string $file, ?string $symbol): array
    {
        return match (true) {
            ! is_null($file) => [self::read($file), true],
            ! is_null($symbol) && self::ships($symbol) => [Ricon_get($symbol), false],
            default => [R_INVALID_ID, false],
        };
    }

    /**
     * @throws ToolkitException When Raven reads no picture in it.
     */
    public static function read(string $file): int
    {
        $texture = Rtexture_create_from_file($file);

        return Rtexture_is_valid($texture) === R_TRUE ? $texture : throw new ToolkitException("'{$file}' is not an image Raven reads.");
    }

    /**
     * Whether Raven ships an icon of that name ("search", or "raven:search").
     */
    public static function ships(string $name): bool
    {
        if (is_null(self::$symbols)) {
            self::$symbols = [];
            for ($index = 0, $count = Ricon_get_count(); $index < $count; $index++) {
                self::$symbols[(string) Ricon_get_name($index)] = true;
            }
        }

        return isset(self::$symbols[str_starts_with($name, 'raven:') ? substr($name, 6) : $name]);
    }
}
