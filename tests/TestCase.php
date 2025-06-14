<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests;

use HaakCo\PostgresHelper\PostgresHelperServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use DatabaseTransactions;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate');
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            PostgresHelperServiceProvider::class,
        ];
    }

    #[\Override]
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use PostgreSQL
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_TEST_HOST', '127.0.0.1'),
            'port' => env('DB_TEST_PORT', '5433'),
            'database' => env('DB_TEST_DATABASE', 'laravel_postgres_helper_test'),
            'username' => env('DB_TEST_USERNAME', 'postgres'),
            'password' => env('DB_TEST_PASSWORD', 'postgres'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
    }
}
