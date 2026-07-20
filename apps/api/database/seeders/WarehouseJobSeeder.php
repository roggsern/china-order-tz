<?php

namespace Database\Seeders;

use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\WarehouseJob;
use App\Services\Warehouse\WarehouseEngine;
use Illuminate\Database\Seeder;

/**
 * Realistic warehouse pick/pack jobs for existing fulfillments.
 */
class WarehouseJobSeeder extends Seeder
{
    public function run(): void
    {
        $engine = app(WarehouseEngine::class);
        $picker = Admin::query()->first();
        $packer = Admin::query()->skip(1)->first() ?? $picker;

        $fulfillments = Fulfillment::query()
            ->whereDoesntHave('warehouseJob')
            ->with('order')
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        $statuses = [
            WarehouseJobStatus::Pending,
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
            WarehouseJobStatus::ReadyToShip,
        ];

        foreach ($fulfillments as $index => $fulfillment) {
            if ($fulfillment->order === null) {
                continue;
            }

            try {
                $job = $engine->createForFulfillment($fulfillment, 'Seeded warehouse job');
            } catch (\Throwable) {
                continue;
            }

            $target = $statuses[$index % count($statuses)];
            $current = WarehouseJobStatus::Pending;

            while ($current !== $target && $current->nextForward() !== null) {
                $next = $current->nextForward();
                if ($next === null) {
                    break;
                }
                try {
                    $job = $engine->updateStatus($job, ['status' => $next->value]);
                    $current = $next;
                } catch (\Throwable) {
                    break;
                }
            }

            if ($picker !== null && $current !== WarehouseJobStatus::Pending) {
                $engine->assignPicker($job, ['picker_id' => $picker->id]);
            }
            if ($packer !== null && in_array($current, [
                WarehouseJobStatus::Packing,
                WarehouseJobStatus::Packed,
                WarehouseJobStatus::ReadyToShip,
            ], true)) {
                $engine->assignPacker($job, ['packer_id' => $packer->id]);
            }
        }

        // Ensure ready-for-shipping fulfillments used by shipment seeding have ready warehouse jobs.
        Fulfillment::query()
            ->where('status', 'ready_for_shipping')
            ->with('warehouseJob')
            ->get()
            ->each(function (Fulfillment $fulfillment) use ($engine): void {
                $job = $fulfillment->warehouseJob
                    ?? $engine->createForFulfillment($fulfillment);

                if ($job->status !== WarehouseJobStatus::ReadyToShip) {
                    foreach ([
                        WarehouseJobStatus::Picking,
                        WarehouseJobStatus::Picked,
                        WarehouseJobStatus::Packing,
                        WarehouseJobStatus::Packed,
                        WarehouseJobStatus::ReadyToShip,
                    ] as $step) {
                        if ($job->status->canTransitionTo($step) || $job->status === $step) {
                            if ($job->status !== $step && $job->status->canTransitionTo($step)) {
                                $job = $engine->updateStatus($job, ['status' => $step->value]);
                            }
                        } elseif ($job->status->nextForward() !== null) {
                            $job = $engine->updateStatus($job, [
                                'status' => $job->status->nextForward()->value,
                            ]);
                        }
                        if ($job->status === WarehouseJobStatus::ReadyToShip) {
                            break;
                        }
                    }

                    // Force-forward remaining steps.
                    while ($job->status !== WarehouseJobStatus::ReadyToShip
                        && $job->status->nextForward() !== null
                    ) {
                        $job = $engine->updateStatus($job, [
                            'status' => $job->status->nextForward()->value,
                        ]);
                    }
                }
            });

        $this->command?->info('Warehouse jobs: '.WarehouseJob::query()->count());
    }
}
