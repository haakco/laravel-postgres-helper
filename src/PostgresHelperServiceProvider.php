<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper;

use Illuminate\Support\ServiceProvider;

class PostgresHelperServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'haakco');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'haakco');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/postgreshelper.php', 'postgreshelper');

        // Register the service the package provides.
        $this->app->singleton('postgreshelper', function ($app) {
            return new PostgresHelper;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['postgreshelper'];
    }

    /**
     * Console-specific booting.
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/postgreshelper.php' => config_path('postgreshelper.php'),
        ], 'postgreshelper.config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'postgreshelper.migrations');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/haakco'),
        ], 'postgreshelper.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/haakco'),
        ], 'postgreshelper.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/haakco'),
        ], 'postgreshelper.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
