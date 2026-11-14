<?php

namespace Jovian\Toolkits\Raven\Providers;

use Jovian\Toolkits\Raven\RavenBridge;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
    }
}