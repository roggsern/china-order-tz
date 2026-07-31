<?php

namespace App\Services\Warehouse;

use App\Enums\WarehouseJobStatus;
use App\Enums\WarehousePackingStatus;
use App\Events\Audit\PackingCompletedAudit;
use App\Events\Audit\PackingStartedAudit;
use App\Events\Warehouse\PackingCompleted;
use App\Events\Warehouse\PackingStarted;
use App\Models\Admin;
use App\Models\WarehouseJob;
use App\Models\WarehousePackingLine;
use App\Models\WarehousePackingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehousePackingService
{
    public function __construct(
        private readonly WarehouseEngine $warehouseEngine,
    ) {}

    public function createForJob(WarehouseJob $job, ?Admin $packer = null): WarehousePackingRecord
    {
        $job->loadMissing(['order.items', 'packingRecord']);

        if ($job->packingRecord !== null) {
            return $this->show($job->packingRecord);
        }

        if ($job->order === null) {
            throw ValidationException::withMessages(['order' => ['Warehouse job has no order.']]);
        }

        return DB::transaction(function () use ($job, $packer): WarehousePackingRecord {
            $record = WarehousePackingRecord::query()->create([
                'warehouse_job_id' => $job->id,
                'packer_id' => $packer?->id ?? $job->packer_id,
                'status' => WarehousePackingStatus::Pending,
                'package_status' => 'pending',
            ]);

            foreach ($job->order->items as $item) {
                WarehousePackingLine::query()->create([
                    'packing_record_id' => $record->id,
                    'order_item_id' => $item->id,
                    'quantity' => (int) $item->quantity,
                    'packed_quantity' => 0,
                ]);
            }

            return $this->show($record);
        });
    }

    public function show(WarehousePackingRecord $record): WarehousePackingRecord
    {
        return $record->load([
            'warehouseJob.order.user',
            'packer',
            'lines.orderItem',
        ]);
    }

    public function start(WarehousePackingRecord $record, Admin $admin, ?string $notes = null): WarehousePackingRecord
    {
        return DB::transaction(function () use ($record, $admin, $notes): WarehousePackingRecord {
            $status = $this->resolveStatus($record);

            if ($status->isTerminal()) {
                throw ValidationException::withMessages(['status' => ['Packing record is closed.']]);
            }

            $record->packer_id = $admin->id;
            $record->status = WarehousePackingStatus::InProgress;
            $record->package_status = 'packing';
            $record->started_at = $record->started_at ?? now();
            if ($notes !== null) {
                $record->notes = $notes;
            }
            $record->save();

            $job = $record->warehouseJob;
            if ($job !== null) {
                $jobStatus = $job->status instanceof WarehouseJobStatus
                    ? $job->status
                    : WarehouseJobStatus::from((string) $job->status);

                if ($jobStatus === WarehouseJobStatus::Picked) {
                    $this->warehouseEngine->assignPacker($job, ['packer_id' => $admin->id]);
                    $this->warehouseEngine->updateStatus($job, ['status' => WarehouseJobStatus::Packing->value]);
                } elseif ($jobStatus === WarehouseJobStatus::Packing && $job->packer_id === null) {
                    $this->warehouseEngine->assignPacker($job, ['packer_id' => $admin->id]);
                }
            }

            event(new PackingStarted($record->fresh(), $admin));
            event(PackingStartedAudit::fromPackingRecord($record->fresh(), $admin));

            return $this->show($record->fresh());
        });
    }

    /**
     * @param  array{packed_quantity: int}  $input
     */
    public function updateLine(WarehousePackingLine $line, array $input): WarehousePackingLine
    {
        return DB::transaction(function () use ($line, $input): WarehousePackingLine {
            $record = $line->packingRecord()->lockForUpdate()->firstOrFail();
            $status = $this->resolveStatus($record);

            if ($status->isTerminal()) {
                throw ValidationException::withMessages(['packing' => ['Packing record is closed.']]);
            }

            $packed = max(0, (int) ($input['packed_quantity'] ?? 0));
            $required = (int) $line->quantity;

            if ($packed > $required) {
                throw ValidationException::withMessages([
                    'packed_quantity' => ["Packed quantity cannot exceed required quantity ({$required})."],
                ]);
            }

            $line->packed_quantity = $packed;
            $line->save();

            if ($status === WarehousePackingStatus::Pending) {
                $record->status = WarehousePackingStatus::InProgress;
                $record->package_status = 'packing';
                $record->started_at = $record->started_at ?? now();
                $record->save();
            }

            return $line->fresh(['orderItem']);
        });
    }

    public function complete(
        WarehousePackingRecord $record,
        Admin $admin,
        ?string $notes = null,
        ?string $packageStatus = null,
    ): WarehousePackingRecord {
        return DB::transaction(function () use ($record, $admin, $notes, $packageStatus): WarehousePackingRecord {
            $record = $this->show($record);

            foreach ($record->lines as $line) {
                if ((int) $line->packed_quantity < (int) $line->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => ['All items must be fully packed before completion.'],
                    ]);
                }
            }

            $record->status = WarehousePackingStatus::Completed;
            $record->package_status = $packageStatus ?? 'packed';
            $record->completed_at = now();
            if ($notes !== null) {
                $record->notes = $notes;
            }
            $record->save();

            $job = $record->warehouseJob;
            if ($job !== null) {
                $jobStatus = $job->status instanceof WarehouseJobStatus
                    ? $job->status
                    : WarehouseJobStatus::from((string) $job->status);

                if (in_array($jobStatus, [WarehouseJobStatus::Picked, WarehouseJobStatus::Packing], true)) {
                    $this->warehouseEngine->updateStatus($job, ['status' => WarehouseJobStatus::Packed->value]);
                }
            }

            event(new PackingCompleted($record->fresh(), $admin));
            event(PackingCompletedAudit::fromPackingRecord($record->fresh(), $admin));

            return $this->show($record->fresh());
        });
    }

    private function resolveStatus(WarehousePackingRecord $record): WarehousePackingStatus
    {
        return $record->status instanceof WarehousePackingStatus
            ? $record->status
            : WarehousePackingStatus::from((string) $record->status);
    }
}
