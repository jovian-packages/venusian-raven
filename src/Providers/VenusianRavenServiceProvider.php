<?php

namespace Jovian\Toolkits\Raven\Providers;

use Jovian\Toolkits\Raven\RavenAdapterDriver;
use Surface\Toolkits\ToolkitAdapterServiceProvider;

class VenusianRavenServiceProvider extends ToolkitAdapterServiceProvider
{
    protected string $toolkit = 'raven';

    protected string $driver = RavenAdapterDriver::class;

    protected string $config = __DIR__.'/../../config/raven.php';
}
