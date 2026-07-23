<?php

namespace App\Support\Ops;

use App\Support\Monitoring\QueueHealth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RC1 ops rebuild Phase 2 — operational health probes (safe for public responses).
 */
final class OperationalHealth
{
    public const HEARTBEAT_CACHE_KEY = 'ops:scheduler:heartbeat';

    public const RUNTIME_STARTED_CACHE_KEY = 'ops:runtime:started_at';

    /**
     * @return array{
     *     status: string,
     *     checks: array<string, bool|null>,
     *     critical_ok: bool,
     *     details?: array<string, mixed>
     * }
     */
    public static function probe(bool $includeDiagnostics = false): array
    {
        try {
            $timings = [];

            $checks = [
                'database' => self::timed('database', self::database(...), $timings),
                'queue' => self::timed('queue', self::queue(...), $timings),
                'cache' => self::timed('cache', self::cache(...), $timings),
                'storage' => self::timed('storage', self::storage(...), $timings),
                'scheduler' => self::timed('scheduler', self::scheduler(...), $timings),
                'environment' => self::timed('environment', self::environment(...), $timings),
            ];

            $criticalOk = ($checks['database'] === true) && ($checks['storage'] === true);

            $payload = [
                'status' => self::resolveStatus($criticalOk, $checks),
                'checks' => $checks,
                'critical_ok' => $criticalOk,
            ];

            if ($includeDiagnostics) {
                $payload['details'] = self::safeDetails($timings);
            }

            return $payload;
        } catch (\Throwable) {
            return [
                'status' => 'unhealthy',
                'checks' => [
                    'database' => false,
                    'queue' => false,
                    'cache' => false,
                    'storage' => false,
                    'scheduler' => false,
                    'environment' => false,
                ],
                'critical_ok' => false,
            ];
        }
    }

    /**
     * @param  array<string, bool|null>  $checks
     */
    private static function resolveStatus(bool $criticalOk, array $checks): string
    {
        if (! $criticalOk) {
            return 'unhealthy';
        }

        // Soft services: null (e.g. queue/scheduler startup grace) does not degrade.
        if (($checks['cache'] !== true)
            || ($checks['queue'] === false)
            || ($checks['scheduler'] === false)
            || ($checks['environment'] !== true)) {
            return 'degraded';
        }

        return 'ok';
    }

    /**
     * @param  callable(): (bool|null)  $probe
     * @param  array<string, float>  $timings
     */
    private static function timed(string $name, callable $probe, array &$timings): bool|null
    {
        $started = hrtime(true);

        try {
            return $probe();
        } catch (\Throwable) {
            return false;
        } finally {
            $timings[$name] = round((hrtime(true) - $started) / 1_000_000, 2);
        }
    }

    private static function database(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function cache(): bool
    {
        try {
            $key = 'ops:health:cache-probe';
            $token = 'ok-'.uniqid('', true);
            Cache::put($key, $token, 60);

            return Cache::get($key) === $token;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function storage(): bool
    {
        try {
            $disk = Storage::disk('local');
            $path = 'ops/health-'.uniqid('', true).'.txt';
            $disk->put($path, 'ok');
            $ok = $disk->get($path) === 'ok';
            $disk->delete($path);

            $publicRoot = storage_path('app/public');
            if (! is_dir($publicRoot) && ! @mkdir($publicRoot, 0755, true) && ! is_dir($publicRoot)) {
                return false;
            }

            return $ok && is_writable(storage_path('app'));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Soft queue heartbeat via QueueHealth.
     *
     * @return bool|null true fresh, false dead/stale, null startup grace
     */
    private static function queue(): ?bool
    {
        try {
            if (config('queue.default') === 'sync') {
                return true;
            }

            $health = app(QueueHealth::class);
            $probe = $health->probe();

            if (($probe['checks']['connection'] ?? false) !== true) {
                return false;
            }

            // Prefer explicit heartbeat semantics (TTL / grace from monitoring config).
            return $health->workerHeartbeat();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Scheduler liveness via ops:scheduler:heartbeat (RC1-G4C1).
     *
     * @return bool|null true fresh, false stale/dead, null startup grace
     */
    private static function scheduler(): ?bool
    {
        try {
            if (config('ops.bypass_scheduler_health')) {
                return true;
            }

            $maxAgeMinutes = max(1, (int) config('ops.scheduler_heartbeat_max_age_minutes', 10));
            $graceMinutes = max(0, (int) config('ops.scheduler_startup_grace_minutes', 5));

            $raw = Cache::get(self::HEARTBEAT_CACHE_KEY);
            if (is_string($raw) && $raw !== '') {
                try {
                    $at = Carbon::parse($raw);

                    return $at->greaterThan(now()->subMinutes($maxAgeMinutes));
                } catch (\Throwable) {
                    return false;
                }
            }

            if (self::withinSchedulerStartupGrace($graceMinutes)) {
                return null;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function withinSchedulerStartupGrace(int $graceMinutes): bool
    {
        if ($graceMinutes <= 0) {
            return false;
        }

        $started = Cache::get(self::RUNTIME_STARTED_CACHE_KEY);
        if (! is_string($started) || $started === '') {
            return false;
        }

        try {
            return Carbon::parse($started)->greaterThan(now()->subMinutes($graceMinutes));
        } catch (\Throwable) {
            return false;
        }
    }

    private static function environment(): bool
    {
        try {
            $env = (string) app()->environment();

            return $env !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, float>  $timings
     * @return array<string, mixed>
     */
    private static function safeDetails(array $timings): array
    {
        return [
            'environment' => app()->environment(),
            'queue_connection' => config('queue.default'),
            'cache_store' => config('cache.default'),
            'filesystem_disk' => config('filesystems.default'),
            'db_connection' => config('database.default'),
            'monitoring_release' => config('monitoring.release'),
            'queue_worker_heartbeat_ttl_seconds' => config('monitoring.queue.worker_heartbeat_ttl_seconds'),
            'queue_worker_startup_grace_seconds' => config('monitoring.queue.worker_startup_grace_seconds'),
            'scheduler_heartbeat_max_age_minutes' => config('ops.scheduler_heartbeat_max_age_minutes'),
            'scheduler_startup_grace_minutes' => config('ops.scheduler_startup_grace_minutes'),
            'timings_ms' => $timings,
            // Never include credentials, DSNs with passwords, tokens, or webhook secrets.
        ];
    }
}
