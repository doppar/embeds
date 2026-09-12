<?php

namespace Doppar\Embeds;

use Doppar\Embeds\Drivers\BruteForceDriver;
use Doppar\Embeds\Drivers\PgVectorDriver;
use Doppar\Embeds\Drivers\RedisDriver;
use Phaseolies\Launchers\GhostableLauncher;
use Phaseolies\Launchers\ServiceLauncher;

class EmbedsLauncher extends ServiceLauncher implements GhostableLauncher
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(BruteForceDriver::class, fn() => new BruteForceDriver());
        $this->app->singleton(PgVectorDriver::class, fn() => new PgVectorDriver());
        $this->app->singleton(RedisDriver::class, fn() => new RedisDriver());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function launch(): void
    {
        $this->loadMigrations(__DIR__ . '/database/migrations');

        $this->publishes([
            __DIR__ . '/database/migrations' => schema_path('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/config/embeds.php' => config_path('embeds.php'),
        ], 'config');
    }

    /**
     * Get the services that should ghost-load this launcher.
     *
     * @return array<int, string>
     */
    public function ghosts(): array
    {
        return [
            BruteForceDriver::class,
            PgVectorDriver::class,
            RedisDriver::class,
        ];
    }
}
