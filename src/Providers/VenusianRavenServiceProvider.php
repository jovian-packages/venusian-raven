<?php

namespace Jovian\Toolkits\Raven\Providers;

use Jovian\Toolkits\Raven\Input\RavenInputBackend;
use Jovian\Toolkits\Raven\RavenBridge;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Surface\HumanInput\HumanInput;
use Surface\Toolkits\Toolkits;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class VenusianRavenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->registerSingleton(RavenBridge::class, fn (FrameworkCore $app) => Toolkits::bridge('raven'));
        $this->app->alias('raven-bridge', RavenBridge::class);
    }

    /**
     * Publishes and merges config/raven.php, then registers the toolkit's driver on the bridge:
     * Surface names no toolkit.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function boot(): void
    {
        $source = realpath($raw = __DIR__.'/../../config/raven.php') ?: $raw;
        $this->publishes([$source => $this->app->configPath('raven.php')], 'raven-config');
        $this->mergeConfigFrom($source, 'raven');

        $toolkits = $this->app->get('toolkit-bridge-manager');
        $toolkits->extend('raven', fn ($app): RavenBridge => new RavenBridge($app));

        // the 'raven' input backend, when the application turned human input on
        if (HumanInput::enabled()) {
            HumanInput::manager()->extend('raven', fn (): RavenInputBackend => new RavenInputBackend());
        }
    }
}