<?php

namespace Jovian\Toolkits\Raven\Services;

use InvalidArgumentException;
use Surface\Contracts\Toolkits\Services\Desktop;
use Surface\Toolkits\Services\WebAddress;

/** Ropen_url: the OS opens a file with its application, a web address in the browser. */
final class RavenDesktop implements Desktop
{
    public function open(string $path): void
    {
        $full = realpath($path);
        if ($full === false) {
            throw new InvalidArgumentException("Nothing at {$path} to open.");
        }
        Ropen_url('file://'.implode('/', array_map('rawurlencode', explode('/', $full))));
    }

    public function browse(string $url): void
    {
        Ropen_url(WebAddress::checked($url));
    }
}
