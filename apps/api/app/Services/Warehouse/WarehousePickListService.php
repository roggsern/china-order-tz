<?php

namespace App\Services\Warehouse;

use App\Models\WarehouseJob;
use App\Models\WarehousePickList;
use App\Models\WarehousePickListLine;
use App\Models\OrderItem;
use App\Models\ProductVariantWarehouseBin;
use App\Enums\WarehousePickListLineStatus;
use App\Enums\WarehousePickListStatus;
use App\Enums\WarehouseJobStatus;
use App\Events\Audit\PickCompletedAudit;
use App\Events\Audit\PickStartedAudit;
use App\Events\Warehouse\PickCompleted;
use App\Events\Warehouse\PickStarted;
use App\Models\Admin;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehousePickListService
{
    public function __construct(
        private readonly WarehouseEngine $warehouseEngine,
    ) {}

    public function createForJob(WarehouseJob $job, ?Admin $picker = null): WarehousePickList
    {
        $job->loadMissing(['order.items', 'pickList']);

        if ($job->pickList !== null) {
            return $this->show($job->pickList);
        }

        if ($job->order === null) {
            throw ValidationException::withMessages(['order' => ['Warehouse job has no order.']]);
        }

        return DB::transaction(function () use ($job, $picker): WarehousePickList {
            $pickList = WarehousePickList::query()->create([
                'warehouse_job_id' => $job->id,
                'order_id' => $job->order_id,
                'picker_id' => $picker?->id ?? $job->picker_id,
                'status' => WarehousePickListStatus::Pending,
            ]);

            foreach ($job->order->items as $item) {
                /** @var OrderItem $item */
                $binId = $this->resolveSuggestedBinId($item);

                WarehousePickListLine::query()->create([
                    'pick_list_id' => $pickList->id,
                    'order_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => (string) ($item->product_name_snapshot ?? $item->product_name ?? 'Item'),
                    'sku' => $item->variant_sku_snapshot ?? $item->sku_snapshot ?? $item->sku,
                    'quantity' => (int) $item->quantity,
                    'picked_quantity' => 0,
                    'warehouse_bin_id' => $binId,
                    'status' => WarehousePickListLineStatus::Pending,
                ]);
            }

            return $this->show($pickList);
        });
    }

    public function show(WarehousePickList $pickList): WarehousePickList
    {
        return $pickList->load([
            'warehouseJob.order.user',
            'order.user',
            'picker',
            'lines.orderItem',
            'lines.productVariant',
            'lines.warehouseBin.zone.facility',
        ]);
    }

    public function start(WarehousePickList $pickList, Admin $admin): WarehousePickList
    {
        return DB::transaction(function () use ($pickList, $admin): WarehousePickList {
            $current = $this->resolveStatus($pickList);

            if ($current->isTerminal()) {
                throw ValidationException::withMessages(['status' => ['Pick list is already closed.']]);
            }

            $pickList->picker_id = $admin->id;
            $pickList->status = WarehousePickListStatus::InProgress;
            $pickList->started_at = $pickList->started_at ?? now();
            $pickList->save();

            $job = $pickList->warehouseJob;
            if ($job !== null) {
                $jobStatus = $job->status instanceof WarehouseJobStatus
                    ? $job->status
                    : WarehouseJobStatus::from((string) $job->status);

                if ($jobStatus === WarehouseJobStatus::Pending) {
                    $this->warehouseEngine->assignPicker($job, ['picker_id' => $admin->id]);
                    $this->warehouseEngine->updateStatus($job, ['status' => WarehouseJobStatus::Picking->value]);
                } elseif ($jobStatus === WarehouseJobStatus::Picking && $job->picker_id === null) {
                    $this->warehouseEngine->assignPicker($job, ['picker_id' => $admin->id]);
                }
            }

            event(new PickStarted($pickList->fresh(), $admin));
            event(PickStartedAudit::fromPickList($pickList->fresh(), $admin));

            return $this->show($pickList->fresh());
        });
    }

    /**
     * @param  array{picked_quantity: int}  $input
     */
    public function updateLine(WarehousePickListLine $line, array $input): WarehousePickListLine
    {
        return DB::transaction(function () use ($line, $input): WarehousePickListLine {
            $pickList = $line->pickList()->lockForUpdate()->firstOrFail();
            $status = $this->resolveStatus($pickList);

            if ($status->isTerminal()) {
                throw ValidationException::withMessages(['pick_list' => ['Pick list is closed.']]);
            }

            $picked = max(0, (int) ($input['picked_quantity'] ?? 0));
            $required = (int) $line->quantity;

            if ($picked > $required) {
                throw ValidationException::withMessages([
                    'picked_quantity' => ["Picked quantity cannot exceed required quantity ({$required})."],
                ]);
            }

            $line->picked_quantity = $picked;
            $line->status = match (true) {
                $picked === 0 => WarehousePickListLineStatus::Pending,
                $picked < $required => WarehousePickListLineStatus::Partial,
                default => WarehousePickListLineStatus::Picked,
            };
            $line->save();

            if ($status === WarehousePickListStatus::Pending) {
                $pickList->status = WarehousePickListStatus::InProgress;
                $pickList->started_at = $pickList->started_at ?? now();
                $pickList->save();
            }

            return $line->fresh(['orderItem', 'warehouseBin']);
        });
    }

    public function complete(WarehousePickList $pickList, Admin $admin): WarehousePickList
    {
        return DB::transaction(function () use ($pickList, $admin): WarehousePickList {
            $pickList = $this->show($pickList);

            foreach ($pickList->lines as $line) {
                if ((int) $line->picked_quantity < (int) $line->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => ["Line {$line->product_name} is not fully picked ({$line->picked_quantity}/{$line->quantity})."],
                    ]);
                }
            }

            $pickList->status = WarehousePickListStatus::Completed;
            $pickList->completed_at = now();
            $pickList->save();

            $job = $pickList->warehouseJob;
            if ($job !== null) {
                $jobStatus = $job->status instanceof WarehouseJobStatus
                    ? $job->status
                    : WarehouseJobStatus::from((string) $job->status);

                if (in_array($jobStatus, [WarehouseJobStatus::Pending, WarehouseJobStatus::Picking], true)) {
                    $this->warehouseEngine->updateStatus($job, ['status' => WarehouseJobStatus::Picked->value]);
                }
            }

            event(new PickCompleted($pickList->fresh(), $admin));
            event(PickCompletedAudit::fromPickList($pickList->fresh(), $admin));

            return $this->show($pickList->fresh());
        });
    }

    private function resolveSuggestedBinId(OrderItem $item): ?string
    {
        if ($item->product_variant_id === null) {
            return null;
        }

        $assignment = ProductVariantWarehouseBin::query()
            ->where('product_variant_id', $item->product_variant_id)
            ->where('is_primary', true)
            ->value('warehouse_bin_id');

        return is_string($assignment) ? $assignment : null;
    }

    private function resolveStatus(WarehousePickList $pickList): WarehousePickListStatus
    {
        return $pickList->status instanceof WarehousePickListStatus
            ? $pickList->status
            : WarehousePickListStatus::from((string) $pickList->status);
    }
}
