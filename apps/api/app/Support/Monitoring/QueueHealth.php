<?php

namespace App\Support\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class QueueHealth
{
    public const WORKER_HEARTBEAT_KEY = 'ops:queue:worker_heartbeat';

    public const WORKER_STARTED_KEY = 'ops:queue:worker_liveness_started_at';

    /**
     * @return array{
     *     ok: bool,
     *     checks: array<string, bool|null|int>,
     *     messages: list<string>
     * }
     */
    public function probe(): array
    {
        $messages = [];
        $checks = [
            'connection' => false,
            'failed_jobs' => 0,
            'pending_jobs' => 0,
            'failed_ok' => true,
            'pending_ok' => true,
            'worker_heartbeat' => null,
        ];

        try {
            $connection = config('queue.default');
            if ($connection === 'sync') {
                $checks['connection'] = true;
            } elseif ($connection === 'database') {
                $checks['connection'] = Schema::hasTable('jobs') && Schema::hasTable('failed_jobs');
                if ($checks['connection']) {
                    $checks['failed_jobs'] = (int) DB::table('failed_jobs')->count();
                    $checks['pending_jobs'] = (int) DB::table('jobs')->count();
                }
            } else {
                app('queue')->connection($connection);
                $checks['connection'] = true;
            }
        } catch (\Throwable) {
            $checks['connection'] = false;
            $messages[] = 'Queue connection unavailable.';
        }

        $failedWarn = max(0, (int) config('monitoring.queue.failed_jobs_warning', 10));
        $pendingWarn = max(0, (int) config('monitoring.queue.pending_jobs_warning', 100));

        $checks['failed_ok'] = ((int) $checks['failed_jobs']) <= $failedWarn;
        $checks['pending_ok'] = ((int) $checks['pending_jobs']) <= $pendingWarn;

        if (! $checks['failed_ok']) {
            $messages[] = 'Failed jobs above warning threshold.';
        }
        if (! $checks['pending_ok']) {
            $messages[] = 'Pending jobs above warning threshold.';
        }

        // Sync driver has no dedicated worker process.
        if (config('queue.default') === 'sync') {
            $checks['worker_heartbeat'] = true;
        } else {
            $checks['worker_heartbeat'] = $this->workerHeartbeat();
        }

        if ($checks['worker_heartbeat'] === false) {
            $messages[] = 'Queue worker heartbeat stale or missing past grace (worker may be dead).';
        }

        $ok = $checks['connection'] === true
            && $checks['failed_ok'] === true
            && $checks['pending_ok'] === true
            && $checks['worker_heartbeat'] !== false;

        return compact('ok', 'checks', 'messages');
    }

    public function touchWorkerHeartbeat(): void
    {
        try {
            $ttl = $this->heartbeatTtlSeconds();
            Cache::put(self::WORKER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addSeconds($ttl * 2));
            Cache::add(self::WORKER_STARTED_KEY, now()->toIso8601String(), now()->addDays(30));
        } catch (\Throwable) {
            // Never break the worker loop for monitoring.
        }
    }

    /**
     * Worker liveness:
     * - true: fresh heartbeat within TTL
     * - false: expired heartbeat, or missing past startup grace
     * - null: missing but still inside startup grace
     */
    public function workerHeartbeat(): ?bool
    {
        if (config('monitoring.queue.bypass_worker_health')) {
            return true;
        }

        $ttl = $this->heartbeatTtlSeconds();
        $grace = $this->startupGraceSeconds();

        $raw = Cache::get(self::WORKER_HEARTBEAT_KEY);
        if (is_string($raw) && $raw !== '') {
            try {
                $at = Carbon::parse($raw);

                return $at->greaterThan(now()->subSeconds($ttl));
            } catch (\Throwable) {
                return false;
            }
        }

        // No heartbeat — idle queue with a dead worker must become detectable after grace.
        $this->ensureLivenessAnchor();

        if ($this->withinStartupGrace($grace)) {
            return null;
        }

        return false;
    }

    private function heartbeatTtlSeconds(): int
    {
        return max(30, (int) config('monitoring.queue.worker_heartbeat_ttl_seconds', 300));
    }

    private function startupGraceSeconds(): int
    {
        return max(0, (int) config('monitoring.queue.worker_startup_grace_seconds', 120));
    }

    private function ensureLivenessAnchor(): void
    {
        Cache::add(self::WORKER_STARTED_KEY, now()->toIso8601String(), now()->addDays(30));
    }

    private function withinStartupGrace(int $graceSeconds): bool
    {
        if ($graceSeconds <= 0) {
            return false;
        }

        $started = Cache::get(self::WORKER_STARTED_KEY);
        if (! is_string($started) || $started === '') {
            return true;
        }

        try {
            return Carbon::parse($started)->greaterThan(now()->subSeconds($graceSeconds));
        } catch (\Throwable) {
            return false;
        }
    }
}
