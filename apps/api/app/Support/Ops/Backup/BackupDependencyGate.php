<?php

namespace App\Support\Ops\Backup;

interface BackupDependencyGate
{
    /**
     * @return array{
     *     ok: bool,
     *     checks: array<string, bool|null>,
     *     messages: list<string>
     * }
     */
    public function check(bool $requireMysqldump = true): array;

    public function assertReady(bool $requireMysqldump = true): void;
}
