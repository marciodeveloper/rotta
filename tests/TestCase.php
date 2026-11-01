<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->assertSafeTestEnvironmentBeforeBoot();

        parent::setUp();

        if (! app()->environment('testing') || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'rotta_prelaunch_test') {
            throw new LogicException('Tests must use the isolated PostgreSQL rotta_prelaunch_test database.');
        }

        Http::preventStrayRequests();
    }

    private function assertSafeTestEnvironmentBeforeBoot(): void
    {
        $environment = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        $connection = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? null;
        $database = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? null;

        if ($environment !== 'testing' || $connection !== 'pgsql' || $database !== 'rotta_prelaunch_test') {
            throw new LogicException('Unsafe test environment refused before Laravel boot.');
        }
    }
}
