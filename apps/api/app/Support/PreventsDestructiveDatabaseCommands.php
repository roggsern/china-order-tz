<?php

namespace App\Support;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

/**
 * Blocks migrate:fresh / db:wipe / migrate:refresh against shared Compose MySQL
 * unless explicitly allowed. Tests (sqlite / APP_ENV=testing) are unaffected.
 */
final class PreventsDestructiveDatabaseCommands
{
    /** @var list<string> */
    private const DANGEROUS_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'db:wipe',
    ];

    public function __invoke(CommandStarting $event): void
    {
        $command = (string) $event->command;

        if (! in_array($command, self::DANGEROUS_COMMANDS, true)) {
            return;
        }

        if ($this->isExplicitlyAllowed()) {
            return;
        }

        if ($this->isIsolatedTestDatabase()) {
            return;
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        throw new RuntimeException(
            "Blocked destructive command [{$command}] against {$driver} database [{$database}]. ".
            'This protects the Compose development MySQL volume from accidental wipes. '.
            'Use an isolated test DB (phpunit.xml forces sqlite :memory:), or set '.
            'ALLOW_DESTRUCTIVE_DB=true only when you intentionally reset local data, then re-seed.'
        );
    }

    private function isExplicitlyAllowed(): bool
    {
        $flag = env('ALLOW_DESTRUCTIVE_DB', false);

        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }

    private function isIsolatedTestDatabase(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        // Prefer config (may already be sqlite) over process env inherited from Compose.
        return $driver === 'sqlite' && ($database === ':memory:' || str_contains($database, 'testing'));
    }
}
