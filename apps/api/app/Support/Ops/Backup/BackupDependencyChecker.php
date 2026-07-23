<?php

namespace App\Support\Ops\Backup;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * RC1-G4C2 — fail-fast backup dependency verification.
 */
final class BackupDependencyChecker implements BackupDependencyGate
{
    public function __construct(
        private readonly BackupPaths $paths,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     checks: array<string, bool|null>,
     *     messages: list<string>
     * }
     */
    public function check(bool $requireMysqldump = true): array
    {
        try {
            $messages = [];
            $checks = [
                'backup_enabled' => (bool) config('backup.enabled', true),
                'mysqldump' => null,
                'database_connectivity' => null,
                'tar' => false,
                'gzip' => false,
                'backup_root_writable' => false,
                'media_source_readable' => false,
                'destination_config' => false,
            ];

            if (! $checks['backup_enabled']) {
                $checks['mysqldump'] = true;
                $checks['database_connectivity'] = true;
                $checks['tar'] = true;
                $checks['gzip'] = true;
                $checks['backup_root_writable'] = true;
                $checks['media_source_readable'] = true;
                $checks['destination_config'] = true;

                return ['ok' => true, 'checks' => $checks, 'messages' => ['Backups disabled.']];
            }

            if (! $requireMysqldump) {
                $checks['mysqldump'] = true;
                $checks['database_connectivity'] = true;
            } else {
                $bin = (string) config('backup.database.mysqldump_bin', 'mysqldump');
                $resolved = $this->resolveBinary($bin);
                $checks['mysqldump'] = $resolved !== null;
                if (! $checks['mysqldump']) {
                    $messages[] = 'mysqldump binary not found (rebuild PHP image with MySQL 8.4 client, or set BACKUP_MYSQLDUMP_BIN).';
                    Log::error('ops.backup.dependency_missing', [
                        'binary' => 'mysqldump',
                        'configured' => $bin,
                    ]);
                } elseif ((bool) config('backup.database.connectivity_check', true)) {
                    $connectivity = $this->checkDatabaseConnectivity();
                    $checks['database_connectivity'] = $connectivity['ok'];
                    if (! $connectivity['ok']) {
                        $messages[] = $connectivity['message'];
                    }
                } else {
                    $checks['database_connectivity'] = true;
                }
            }

            $checks['tar'] = $this->resolveBinary('tar') !== null;
            if (! $checks['tar']) {
                $messages[] = 'tar binary not found.';
                Log::error('ops.backup.dependency_missing', ['binary' => 'tar']);
            }

            $checks['gzip'] = $this->resolveBinary('gzip') !== null || function_exists('gzopen');
            if (! $checks['gzip']) {
                $messages[] = 'gzip support not found.';
                Log::error('ops.backup.dependency_missing', ['binary' => 'gzip']);
            }

            try {
                $this->paths->ensureLayout();
                $root = $this->paths->root();
                $checks['backup_root_writable'] = is_dir($root) && is_writable($root);
                if (! $checks['backup_root_writable']) {
                    $messages[] = 'Backup root is not writable.';
                }
            } catch (Throwable) {
                $checks['backup_root_writable'] = false;
                $messages[] = 'Backup root is not available.';
            }

            $mediaOk = true;
            foreach (config('backup.media.paths', []) as $relative) {
                $absolute = storage_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relative));
                if (! is_dir($absolute)) {
                    @mkdir($absolute, 0755, true);
                }
                if (! is_dir($absolute) || ! is_readable($absolute)) {
                    $mediaOk = false;
                    break;
                }
            }
            $checks['media_source_readable'] = $mediaOk;
            if (! $mediaOk) {
                $messages[] = 'Media source paths under storage/app are not readable.';
            }

            try {
                app(BackupStorageManager::class)->driver()->validateConfig();
                $checks['destination_config'] = true;
            } catch (Throwable) {
                $checks['destination_config'] = false;
                $messages[] = 'Backup destination configuration invalid.';
                Log::error('ops.backup.destination_invalid', [
                    'driver' => (string) config('backup.destination.driver', 'local'),
                    'error' => 'config_invalid',
                ]);
            }

            $ok = ($checks['mysqldump'] === true)
                && ($checks['database_connectivity'] === true || $checks['database_connectivity'] === null)
                && $checks['tar']
                && $checks['gzip']
                && $checks['backup_root_writable']
                && $checks['media_source_readable']
                && $checks['destination_config'];

            return compact('ok', 'checks', 'messages');
        } catch (Throwable) {
            return [
                'ok' => false,
                'checks' => [
                    'backup_enabled' => (bool) config('backup.enabled', true),
                    'mysqldump' => false,
                    'database_connectivity' => false,
                    'tar' => false,
                    'gzip' => false,
                    'backup_root_writable' => false,
                    'media_source_readable' => false,
                    'destination_config' => false,
                ],
                'messages' => ['Backup dependency check failed unexpectedly.'],
            ];
        }
    }

    public function assertReady(bool $requireMysqldump = true): void
    {
        $result = $this->check($requireMysqldump);
        if (! $result['ok']) {
            throw new RuntimeException(
                'Backup dependencies not satisfied: '.implode(' ', $result['messages'])
            );
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkDatabaseConnectivity(): array
    {
        if (app()->environment('testing')) {
            return ['ok' => true, 'message' => ''];
        }

        if ((string) config('backup.database.driver', 'mysqldump') === 'fake') {
            return ['ok' => true, 'message' => ''];
        }

        $mysqlBin = (string) config('backup.database.mysql_bin', 'mysql');
        if ($this->resolveBinary($mysqlBin) === null) {
            return [
                'ok' => false,
                'message' => 'mysql client binary not found for connectivity check (rebuild PHP image with MySQL 8.4 client, or set BACKUP_MYSQL_BIN).',
            ];
        }

        $defaultsFile = tempnam(sys_get_temp_dir(), 'mycnf');
        if ($defaultsFile === false) {
            return ['ok' => false, 'message' => 'Unable to create database connectivity defaults file.'];
        }

        try {
            BackupDatabaseClient::writeDefaultsFile($defaultsFile);

            $timeout = max(5, min(30, (int) config('backup.database.timeout_seconds', 600)));
            $result = Process::timeout($timeout)
                ->run(BackupDatabaseClient::connectivityArguments($defaultsFile));

            if ($result->successful()) {
                return ['ok' => true, 'message' => ''];
            }

            Log::error('ops.backup.database_connectivity_failed', [
                'exit' => $result->exitCode(),
                'stderr' => self::sanitizeProcessOutput($result->errorOutput()),
            ]);

            return [
                'ok' => false,
                'message' => 'Database connectivity check failed (verify MySQL client, credentials, and BACKUP_DB_SSL_MODE).',
            ];
        } catch (Throwable) {
            Log::error('ops.backup.database_connectivity_failed', [
                'error' => 'connectivity_check_exception',
            ]);

            return [
                'ok' => false,
                'message' => 'Database connectivity check failed unexpectedly.',
            ];
        } finally {
            @unlink($defaultsFile);
        }
    }

    private static function sanitizeProcessOutput(string $output): string
    {
        $output = trim($output);
        if ($output === '') {
            return '';
        }

        return preg_replace('/password[^\s]*/i', '[redacted]', $output) ?? $output;
    }

    private function resolveBinary(string $name): ?string
    {
        if ($name !== '' && (is_file($name) || is_executable($name))) {
            return $name;
        }

        $finder = new ExecutableFinder;

        return $finder->find($name);
    }
}
