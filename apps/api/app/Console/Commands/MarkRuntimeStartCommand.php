<?php

namespace App\Console\Commands;

use App\Support\Monitoring\QueueHealth;
use App\Support\Ops\OperationalHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * RC1 ops rebuild Phase 3B — runtime liveness heartbeat (queue + scheduler grace anchor).
 */
class MarkRuntimeStartCommand extends Command
{
    protected $signature = 'ops:runtime-heartbeat';

    protected $description = 'Refresh queue worker heartbeat and record runtime liveness anchors (idempotent).';

    public function handle(QueueHealth $queueHealth): int
    {
        try {
            $queueHealth->touchWorkerHeartbeat();

            Cache::add(
                OperationalHealth::RUNTIME_STARTED_CACHE_KEY,
                now()->toIso8601String(),
                now()->addDays(30)
            );

            $this->info('Runtime heartbeat updated.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Runtime heartbeat update failed unexpectedly.');

            return self::SUCCESS;
        }
    }
}
