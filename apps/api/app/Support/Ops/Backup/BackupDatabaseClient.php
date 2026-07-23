<?php

namespace App\Support\Ops\Backup;

/**
 * Shared MySQL CLI client settings for mysqldump dependency checks and backups.
 */
final class BackupDatabaseClient
{
    /**
     * @return list<string>
     */
    public static function sslArguments(): array
    {
        $sslMode = strtolower(trim((string) config('backup.database.ssl_mode', 'DISABLED')));
        $sslCa = trim((string) config('backup.database.ssl_ca', ''));

        if ($sslCa !== '') {
            return ['--ssl-mode=VERIFY_CA', '--ssl-ca='.$sslCa];
        }

        return match ($sslMode) {
            '', 'disabled', 'disable' => ['--ssl-mode=DISABLED'],
            'preferred', 'prefer' => ['--ssl-mode=PREFERRED'],
            'required', 'require' => ['--ssl-mode=REQUIRED'],
            'verify_ca' => ['--ssl-mode=VERIFY_CA'],
            'verify_identity' => ['--ssl-mode=VERIFY_IDENTITY'],
            default => ['--ssl-mode=DISABLED'],
        };
    }

    /**
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    public static function connectionConfig(): array
    {
        $connection = (string) config('database.default');
        $cfg = config("database.connections.{$connection}", []);

        if (($cfg['driver'] ?? '') !== 'mysql') {
            throw new \RuntimeException('Database backups require a mysql connection.');
        }

        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (string) ($cfg['port'] ?? '3306');
        $database = (string) ($cfg['database'] ?? '');
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Database name/username missing for backup.');
        }

        return compact('host', 'port', 'database', 'username', 'password');
    }

    public static function writeDefaultsFile(string $path): void
    {
        $cfg = self::connectionConfig();

        @chmod($path, 0600);

        $cnf = "[client]\n"
            ."host={$cfg['host']}\n"
            ."port={$cfg['port']}\n"
            ."user={$cfg['username']}\n"
            .'password="'.addcslashes($cfg['password'], "\\\"\n\r")."\"\n";

        file_put_contents($path, $cnf);
    }

    /**
     * @return list<string>
     */
    public static function connectivityArguments(string $defaultsFile): array
    {
        $mysqlBin = (string) config('backup.database.mysql_bin', 'mysql');

        return array_merge(
            [$mysqlBin, '--defaults-extra-file='.$defaultsFile, '--batch', '--silent'],
            self::sslArguments(),
            ['-e', 'SELECT 1'],
        );
    }
}
