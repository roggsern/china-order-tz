<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| RC1-G4C1 — Production scheduler
|--------------------------------------------------------------------------
| Docker: run `php artisan schedule:work` (see docker-compose.prod.yml `scheduler`).
| Host cron alternative: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
| onOneServer() requires a lock-capable cache store (database/redis).
*/

Schedule::call(function (): void {
    Cache::put('ops:scheduler:heartbeat', now()->toIso8601String(), now()->addMinutes(15));
})->everyMinute()
    ->name('ops-scheduler-heartbeat')
    ->withoutOverlapping(2);

Schedule::command('nmb:reconcile-payments --limit=50')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->name('nmb-reconcile-payments');

Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('queue-prune-failed');

Schedule::command('model:prune')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('model-prune');

Schedule::command('ops:prune-expired-cache')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('ops-prune-expired-cache');

// Redis tagged cache only; no-op / skip when not using Redis.
Schedule::command('cache:prune-stale-tags')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => config('cache.default') === 'redis')
    ->name('cache-prune-stale-tags');

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('sanctum-prune-expired');

Schedule::command('ops:prune-temp-uploads')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('ops-prune-temp-uploads');

/*
|--------------------------------------------------------------------------
| RC1-G4C2 — Backup foundation
|--------------------------------------------------------------------------
*/
Schedule::command('ops:backup-run')
    ->dailyAt((string) config('backup.schedule.daily_at', '02:15'))
    ->withoutOverlapping(120)
    ->onOneServer()
    ->when(fn () => (bool) config('backup.enabled', true))
    ->name('ops-backup-run');

/*
|--------------------------------------------------------------------------
| RC1-G4C3 — Monitoring sweep
|--------------------------------------------------------------------------
*/
Schedule::command('ops:monitoring-sweep')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => (bool) config('monitoring.enabled', true))
    ->name('ops-monitoring-sweep');

Schedule::command('ops:queue-health --alert')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => (bool) config('monitoring.enabled', true))
    ->name('ops-queue-health');
