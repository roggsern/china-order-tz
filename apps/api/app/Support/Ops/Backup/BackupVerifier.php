<?php

namespace App\Support\Ops\Backup;

use RuntimeException;

final class BackupVerifier
{
    /**
     * @return array{ok: bool, checks: array<string, bool|string>, messages: list<string>}
     */
    public function verifyFile(string $path, string $type = 'auto'): array
    {
        $messages = [];
        $checks = [
            'exists' => is_file($path),
            'readable' => is_file($path) && is_readable($path),
            'size_ok' => false,
            'format_ok' => false,
        ];

        if (! $checks['exists']) {
            $messages[] = 'Backup file missing.';

            return ['ok' => false, 'checks' => $checks, 'messages' => $messages];
        }

        $bytes = (int) filesize($path);
        $min = $type === 'media'
            ? (int) config('backup.media.min_bytes', 32)
            : (int) config('backup.database.min_bytes', 64);

        if ($type === 'auto') {
            $min = str_contains($path, 'media')
                ? (int) config('backup.media.min_bytes', 32)
                : (int) config('backup.database.min_bytes', 64);
            $type = str_contains($path, 'media') ? 'media' : 'database';
        }

        $checks['size_ok'] = $bytes >= $min;
        if (! $checks['size_ok']) {
            $messages[] = "Backup too small ({$bytes} bytes).";
        }

        if ($type === 'database' || str_ends_with($path, '.sql.gz')) {
            $checks['format_ok'] = $this->verifyGzipSql($path, $messages);
        } elseif ($type === 'media' || str_ends_with($path, '.tar.gz')) {
            $checks['format_ok'] = $this->verifyGzipTar($path, $messages);
        } else {
            $checks['format_ok'] = true;
        }

        $ok = $checks['exists'] && $checks['readable'] && $checks['size_ok'] && $checks['format_ok'];

        return ['ok' => $ok, 'checks' => $checks, 'messages' => $messages];
    }

    /**
     * @param  list<string>  $messages
     */
    private function verifyGzipSql(string $path, array &$messages): bool
    {
        if (! (bool) config('backup.verification.require_gzip_magic', true)) {
            return true;
        }

        $gz = @gzopen($path, 'rb');
        if ($gz === false) {
            $messages[] = 'Unable to open gzip database dump.';

            return false;
        }

        $head = (string) gzread($gz, 2048);
        gzclose($gz);

        if ($head === '') {
            $messages[] = 'Database dump gzip stream empty.';

            return false;
        }

        if (! preg_match('/(--|\/\*|CREATE|INSERT|DROP|SET|LOCK)/i', $head)) {
            $messages[] = 'Database dump does not look like SQL.';

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $messages
     */
    private function verifyGzipTar(string $path, array &$messages): bool
    {
        if (! (bool) config('backup.verification.require_tar_magic', true)) {
            return true;
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            $messages[] = 'Unable to read media archive.';

            return false;
        }
        $magic = fread($fh, 2);
        fclose($fh);

        if ($magic !== "\x1f\x8b") {
            $messages[] = 'Media archive is not gzip-compressed.';

            return false;
        }

        return true;
    }

    public function assertOk(string $path, string $type = 'auto'): void
    {
        $result = $this->verifyFile($path, $type);
        if (! $result['ok']) {
            throw new RuntimeException(implode(' ', $result['messages']) ?: 'Backup verification failed.');
        }
    }
}
