<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Boots the Laravel application without RefreshDatabase.
 * Use for unit tests that must not run migrate:fresh.
 */
abstract class ApplicationTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->forceIsolatedTestingDatabase();

        parent::setUp();
    }

    protected function forceIsolatedTestingDatabase(): void
    {
        $vars = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DB_HOST' => '',
            'DB_PORT' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
        ];

        foreach ($vars as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
