<?php

namespace App\Support\Ops\Backup;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

final class DatabaseBackupService
{
    public function __construct(
        private readonly BackupPaths $paths,
    ) {}

    /**
     * @return array{path: string, bytes: int}
     */
    public function run(?string $timestamp = null): array
    {
        $this->paths->ensureLayout();
        $target = $this->paths->dailyDatabasePath($timestamp);
        $driver = (string) config('backup.database.driver', 'mysqldump');

        if ($driver === 'fake' || app()->environment('testing')) {
            return $this->writeFakeDump($target);
        }

        return $this->runMysqldump($target);
    }

    /**
     * @return array{path: string, bytes: int}
     */
    private function writeFakeDump(string $target): array
    {
        $sql = "-- China Order TZ fake mysqldump\n"
            ."-- Dump completed\n"
            ."CREATE TABLE IF NOT EXISTS backup_smoke (`id` int);\n";

        $gz = gzopen($target, 'wb9');
        if ($gz === false) {
            throw new RuntimeException('Unable to open gzip target for database backup.');
        }
        gzwrite($gz, $sql);
        gzclose($gz);
        @chmod($target, 0600);

        return ['path' => $target, 'bytes' => (int) filesize($target)];
    }

    /**
     * @return array{path: string, bytes: int}
     */
    private function runMysqldump(string $target): array
    {
        $bin = (string) config('backup.database.mysqldump_bin', 'mysqldump');
        $timeout = max(30, (int) config('backup.database.timeout_seconds', 600));
        $cfg = BackupDatabaseClient::connectionConfig();

        $defaultsFile = tempnam(sys_get_temp_dir(), 'mycnf');
        if ($defaultsFile === false) {
            throw new RuntimeException('Unable to create mysqldump defaults file.');
        }

        BackupDatabaseClient::writeDefaultsFile($defaultsFile);

        $tmpSql = $target.'.partial';
        @unlink($tmpSql);

        try {
            $result = Process::timeout($timeout)
                ->run(array_merge(
                    [
                        $bin,
                        '--defaults-extra-file='.$defaultsFile,
                        '--single-transaction',
                        '--quick',
                        '--routines',
                        '--triggers',
                        '--hex-blob',
                    ],
                    BackupDatabaseClient::sslArguments(),
                    [
                        '--result-file='.$tmpSql,
                        $cfg['database'],
                    ],
                ));

            if ($result->failed()) {
                Log::error('ops.backup.database_failed', [
                    'exit' => $result->exitCode(),
                    'error' => 'mysqldump failed',
                    'stderr' => self::sanitizeProcessOutput($result->errorOutput()),
                ]);
                throw new RuntimeException('mysqldump failed with exit code '.$result->exitCode());
            }

            if (! is_file($tmpSql) || filesize($tmpSql) < 1) {
                throw new RuntimeException('mysqldump produced an empty dump.');
            }

            $this->gzipFile($tmpSql, $target);
            @unlink($tmpSql);
            @chmod($target, 0600);

            $bytes = (int) filesize($target);
            Log::info('ops.backup.database_ok', [
                'file' => basename($target),
                'bytes' => $bytes,
            ]);

            return ['path' => $target, 'bytes' => $bytes];
        } catch (Throwable $e) {
            @unlink($tmpSql);
            @unlink($target);
            throw $e;
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

    private function gzipFile(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($destination, 'wb9');
        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            throw new RuntimeException('Unable to gzip database dump.');
        }

        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            gzwrite($out, $chunk);
        }

        fclose($in);
        gzclose($out);
    }
}
