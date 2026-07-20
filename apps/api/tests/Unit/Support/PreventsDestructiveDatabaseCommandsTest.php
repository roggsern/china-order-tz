<?php

namespace Tests\Unit\Support;

use App\Support\PreventsDestructiveDatabaseCommands;
use Illuminate\Console\Events\CommandStarting;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\ApplicationTestCase;

class PreventsDestructiveDatabaseCommandsTest extends ApplicationTestCase
{
    public function test_allows_destructive_commands_in_testing_environment(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $listener = new PreventsDestructiveDatabaseCommands;
        $listener(new CommandStarting('migrate:fresh', new ArrayInput([]), new NullOutput));
        $listener(new CommandStarting('db:wipe', new ArrayInput([]), new NullOutput));

        $this->addToAssertionCount(1);
    }

    public function test_allows_destructive_commands_against_sqlite_memory(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        $this->app->detectEnvironment(fn (): string => 'local');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.driver', 'sqlite');

        $listener = new PreventsDestructiveDatabaseCommands;
        $listener(new CommandStarting('migrate:fresh', new ArrayInput([]), new NullOutput));

        $this->addToAssertionCount(1);
    }

    public function test_blocks_destructive_commands_against_mysql_when_not_allowed(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        putenv('ALLOW_DESTRUCTIVE_DB=false');
        $_ENV['ALLOW_DESTRUCTIVE_DB'] = 'false';
        $_SERVER['ALLOW_DESTRUCTIVE_DB'] = 'false';
        $this->app->detectEnvironment(fn (): string => 'local');

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.driver', 'mysql');
        config()->set('database.connections.mysql.database', 'china_order_tz');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.username', 'china_order');
        config()->set('database.connections.mysql.password', 'secret');

        $this->assertFalse(app()->environment('testing'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blocked destructive command [migrate:fresh]');

        $listener = new PreventsDestructiveDatabaseCommands;
        $listener(new CommandStarting('migrate:fresh', new ArrayInput([]), new NullOutput));
    }
}
