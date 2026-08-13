<?php

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Guards against orphan Schedule::command() entries that are not registered in Artisan
 * (production symptom: "Command \"ops:…\" is not defined").
 */
class ProductionSchedulerCommandResolutionTest extends TestCase
{
    public function test_every_scheduled_custom_command_resolves_in_artisan(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $undefined = [];

        foreach ($schedule->events() as $event) {
            if (! $event instanceof Event || $event instanceof CallbackEvent) {
                continue;
            }

            $command = $this->extractArtisanCommandName($event);
            if ($command === null) {
                continue;
            }

            if (! array_key_exists($command, Artisan::all())) {
                $undefined[] = $command;
            }
        }

        $this->assertSame(
            [],
            $undefined,
            'Scheduled commands missing from Artisan: '.implode(', ', $undefined),
        );
    }

    public function test_orphan_ops_prune_commands_are_not_scheduled(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $names = $this->scheduledCommandNames($schedule);

        $this->assertNotContains('ops:prune-expired-cache', $names);
        $this->assertNotContains('ops:prune-temp-uploads', $names);
        $this->assertArrayNotHasKey('ops:prune-expired-cache', Artisan::all());
        $this->assertArrayNotHasKey('ops:prune-temp-uploads', Artisan::all());
    }

    public function test_critical_production_schedules_remain_registered(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $names = $this->scheduledCommandNames($schedule);
        $eventNames = [];
        foreach ($schedule->events() as $event) {
            if ($event instanceof Event || $event instanceof CallbackEvent) {
                $eventNames[] = (string) ($event->description ?? '');
            }
        }

        $this->assertContains('nmb:reconcile-payments', $names);
        $this->assertContains('ops:backup-run', $names);
        $this->assertContains('ops:monitoring-sweep', $names);
        $this->assertContains('ops:queue-health', $names);
        $this->assertContains('cache:prune-stale-tags', $names);
        $this->assertContains('sanctum:prune-expired', $names);
        $this->assertContains('queue:prune-failed', $names);
        $this->assertContains('model:prune', $names);

        $joined = implode(' ', $eventNames);
        $this->assertStringContainsString('nmb-reconcile-payments', $joined);
        $this->assertStringContainsString('ops-backup-run', $joined);
        $this->assertStringContainsString('ops-monitoring-sweep', $joined);
        $this->assertStringContainsString('ops-queue-health', $joined);
    }

    /**
     * @return list<string>
     */
    private function scheduledCommandNames(Schedule $schedule): array
    {
        $names = [];
        foreach ($schedule->events() as $event) {
            if (! $event instanceof Event || $event instanceof CallbackEvent) {
                continue;
            }
            $command = $this->extractArtisanCommandName($event);
            if ($command !== null) {
                $names[] = $command;
            }
        }

        return $names;
    }

    private function extractArtisanCommandName(Event $event): ?string
    {
        $raw = (string) $event->command;

        // Typical form: '.../artisan' 'command:name' --options
        if (preg_match("/artisan['\"]?\s+['\"]?([a-z0-9:_-]+)/i", $raw, $matches) === 1) {
            return $matches[1];
        }

        // Some environments store only the command signature fragment.
        if (preg_match('/^([a-z0-9:_-]+)/i', trim($raw), $matches) === 1
            && str_contains($matches[1], ':')) {
            return $matches[1];
        }

        return null;
    }
}
