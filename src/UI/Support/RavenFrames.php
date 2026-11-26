<?php

namespace Jovian\Toolkits\Raven\UI\Support;

use WeakMap;

/**
 * Raven's one frame callback (Rset_frame_callback), shared: it runs once per frame before the
 * layout, and calls refitted() on each primitive that asked to be told. Raven measures every
 * widget before it knows the width it will get, so a primitive whose size follows its width
 * (a fitted picture) reads the width the last layout gave it here and sets its size for this
 * one. Held weakly: a released primitive drops out.
 */
final class RavenFrames
{
    /** @var WeakMap<object, (callable(): void)>|null */
    private static ?WeakMap $watching = null;

    private static bool $installed = false;

    /**
     * Raven's callback, once per process.
     */
    public static function install(): void
    {
        if (self::$installed) {
            return;
        }
        self::$installed = true;
        Rset_frame_callback(static function (): void {
            self::run();
        }, null);
    }

    /**
     * @param callable(): void $refit
     */
    public static function watch(object $primitive, callable $refit): void
    {
        self::$watching ??= new WeakMap();
        self::$watching[$primitive] = $refit;
    }

    public static function forget(object $primitive): void
    {
        if (! is_null(self::$watching)) {
            unset(self::$watching[$primitive]);
        }
    }

    public static function run(): void
    {
        if (is_null(self::$watching)) {
            return;
        }
        foreach (self::$watching as $refit) {
            $refit();
        }
    }
}
