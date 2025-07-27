<?php

namespace Miguilim\Helpers;

use Illuminate\Support\ServiceProvider;

class MiguilimHelpersServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/captcha.php', 'captcha');
        $this->mergeConfigFrom(__DIR__ . '/../config/ip_address.php', 'ip_address');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePublishing();
    }

    /**
     * Configure publishing for the package.
     */
    protected function configurePublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/captcha.php'    => config_path('captcha.php'),
            __DIR__ . '/../config/ip_address.php' => config_path('ip_address.php'),
        ], 'miguilim-helpers-configs');

        $this->publishes([
            __DIR__ . '/../database/migrations/2021_06_28_133032_create_ip_addresses_table.php' => database_path('migrations/2021_06_28_133032_create_ip_addresses_table.php'),
        ], 'miguilim-helpers-migrations');
    }
}
