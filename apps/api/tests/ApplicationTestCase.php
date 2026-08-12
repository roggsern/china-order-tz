<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

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

    /**
     * Compose production images may ship bootstrap/cache/{config,routes}*.php.
     * Cached config ignores sqlite test env; cached routes omit new endpoints.
     * Temporarily hide caches only while creating the test application.
     */
    public function createApplication()
    {
        $hidden = $this->hideBootstrapCachesForProcess();

        try {
            $app = parent::createApplication();
        } finally {
            $this->restoreBootstrapCaches($hidden);
        }

        $this->rebindIsolatedTestingDatabaseOn($app);

        return $app;
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

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function hideBootstrapCachesForProcess(): array
    {
        $cacheDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
        $hidden = [];

        foreach (['config.php', 'routes-v7.php', 'routes.php'] as $name) {
            $path = $cacheDir.DIRECTORY_SEPARATOR.$name;
            if (! is_file($path)) {
                continue;
            }
            $backup = $path.'.phpunit_'.getmypid().'_'.str_replace('.', '', uniqid('', true));
            if (@rename($path, $backup)) {
                $hidden[] = [$backup, $path];
            }
        }

        return $hidden;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $hidden
     */
    private function restoreBootstrapCaches(array $hidden): void
    {
        foreach ($hidden as [$backup, $original]) {
            if (is_file($backup) && ! is_file($original)) {
                @rename($backup, $original);
            } elseif (is_file($backup)) {
                @unlink($backup);
            }
        }
    }

    protected function rebindIsolatedTestingDatabaseOn($app): void
    {
        $app['config']->set('app.env', 'testing');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.driver', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.prefix', '');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

        $app->detectEnvironment(static fn (): string => 'testing');
    }

    protected function refreshApplication()
    {
        parent::refreshApplication();

        DB::purge();
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');
    }
}
