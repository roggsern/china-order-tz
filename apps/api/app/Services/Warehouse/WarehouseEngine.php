<?php

namespace App\Services\Warehouse;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\NotificationEventType;
use App\Enums\WarehouseJobStatus;
use App\Events\Audit\WarehouseJobCreated;
use App\Events\Audit\WarehouseStatusChanged;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\WarehouseJob;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Warehouse Operations Engine — pick/pack workflow after payment + fulfillment.
 * Does not create shipments, print labels, or manage inventory locations.
 */
class WarehouseEngine
{
    public function __construct(
        private readonly WarehouseJobNumberGenerator $numberGenerator,
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * Create warehouse job for a paid order that already has a fulfillment (idempotent).
     */
    public function createForFulfillment(Fulfillment $fulfillment, ?string $notes = null): WarehouseJob
    {
        $fulfillment->loadMissing(['order', 'warehouseJob']);

        $order = $fulfillment->order;
        if ($order === null) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Fulfillment has no order.'],
            ]);
        }

        if ($order->status !== OrderStatus::Paid) {
            throw ValidationException::withMessages([
                'order' => ['Warehouse job can only be created for paid orders.'],
            ]);
        }

        if ($fulfillment->warehouseJob !== null) {
            return $fulfillment->warehouseJob->load(['order.user', 'fulfillment', 'picker', 'packer']);
        }

        return DB::transaction(function () use ($fulfillment, $order, $notes): WarehouseJob {
            /** @var Fulfillment $locked */
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            $existing = WarehouseJob::query()
                ->where('fulfillment_id', $locked->id)
                ->orWhere('order_id', $order->id)
                ->first();

            if ($existing !== null) {
                return $existing->load(['order.user', 'fulfillment', 'picker', 'packer']);
            }

            $job = WarehouseJob::query()->create([
                'order_id' => $order->id,
                'fulfillment_id' => $locked->id,
                'job_number' => $this->numberGenerator->generate(),
                'status' => WarehouseJobStatus::Pending,
                'notes' => $notes,
            ]);

            try {
                event(WarehouseJobCreated::fromJob($job));
            } catch (\Throwable $e) {
                Log::warning('audit.warehouse_job_created_failed', [
                    'warehouse_job_id' => $job->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return $job->fresh(['order.user', 'fulfillment', 'picker', 'packer']) ?? $job;
        });
    }

    public function show(WarehouseJob $job): WarehouseJob
    {
        return $job->loadMissing(['order.user', 'fulfillment', 'picker', 'packer']);
    }

    /**
     * @param  array{status: string, notes?: string|null}  $input
     */
    public function updateStatus(WarehouseJob $job, array $input): WarehouseJob
    {
        $next = WarehouseJobStatus::tryFrom((string) ($input['status'] ?? ''));
        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => ['Invalid warehouse job status.'],
            ]);
        }

        return DB::transaction(function () use ($job, $next, $input): WarehouseJob {
            /** @var WarehouseJob $locked */
            $locked = WarehouseJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            $current = $locked->status instanceof WarehouseJobStatus
                ? $locked->status
                : WarehouseJobStatus::from((string) $locked->status);

            if ($current === $next) {
                if (array_key_exists('notes', $input) && $input['notes'] !== null) {
                    $locked->notes = $input['notes'];
                    $locked->save();
                }

                return $locked->fresh(['order.user', 'fulfillment', 'picker', 'packer']) ?? $locked;
            }

            if (! $current->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Cannot transition warehouse job from [{$current->value}] to [{$next->value}]. Backward transitions are not allowed.",
                    ],
                ]);
            }

            $locked->status = $next;

            if ($next === WarehouseJobStatus::Picked && $locked->picked_at === null) {
                $locked->picked_at = now();
            }
            if ($next === WarehouseJobStatus::Packed && $locked->packed_at === null) {
                $locked->packed_at = now();
            }
            if ($next === WarehouseJobStatus::ReadyToShip && $locked->ready_at === null) {
                $locked->ready_at = now();
            }

            if (array_key_exists('notes', $input) && $input['notes'] !== null) {
                $locked->notes = $input['notes'];
            }

            $locked->save();

            $this->syncFulfillmentForWarehouseStatus($locked, $next);

            $fresh = $locked->fresh(['order.user', 'fulfillment', 'picker', 'packer']) ?? $locked;
            $this->publishWarehouseNotification($fresh, $next);

            try {
                $admin = auth('sanctum')->user();
                event(WarehouseStatusChanged::fromTransition(
                    $fresh,
                    $current,
                    $next,
                    $admin instanceof Admin ? $admin : null,
                ));
            } catch (\Throwable $e) {
                Log::warning('audit.warehouse_status_changed_failed', [
                    'warehouse_job_id' => $fresh->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return $fresh;
        });
    }

    private function publishWarehouseNotification(WarehouseJob $job, WarehouseJobStatus $status): void
    {
        $eventType = match ($status) {
            WarehouseJobStatus::Picking => NotificationEventType::WarehousePickingStarted,
            WarehouseJobStatus::Packed => NotificationEventType::WarehousePacked,
            WarehouseJobStatus::ReadyToShip => NotificationEventType::WarehouseReadyToShip,
            default => null,
        };

        if ($eventType === null) {
            return;
        }

        $job->loadMissing('order.user');
        $user = $job->order?->user;
        if ($user === null) {
            return;
        }

        try {
            $this->notifications->notifyCustomer($eventType, $user, [
                'customer_name' => $user->name,
                'order_number' => $job->order?->order_number,
                'order_id' => $job->order_id,
                'warehouse_job_id' => $job->id,
                'warehouse_status' => $status->value,
            ]);
        } catch (\Throwable $e) {
            Log::warning('notification.warehouse_publish_failed', [
                'warehouse_job_id' => $job->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{picker_id: string|null}  $input
     */
    public function assignPicker(WarehouseJob $job, array $input): WarehouseJob
    {
        return DB::transaction(function () use ($job, $input): WarehouseJob {
            /** @var WarehouseJob $locked */
            $locked = WarehouseJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === WarehouseJobStatus::Cancelled
                || $locked->status === WarehouseJobStatus::ReadyToShip
            ) {
                throw ValidationException::withMessages([
                    'picker_id' => ['Cannot assign picker on a terminal warehouse job.'],
                ]);
            }

            $locked->picker_id = $input['picker_id'] ?? null;
            $locked->save();

            return $locked->fresh(['order.user', 'fulfillment', 'picker', 'packer']) ?? $locked;
        });
    }

    /**
     * @param  array{packer_id: string|null}  $input
     */
    public function assignPacker(WarehouseJob $job, array $input): WarehouseJob
    {
        return DB::transaction(function () use ($job, $input): WarehouseJob {
            /** @var WarehouseJob $locked */
            $locked = WarehouseJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === WarehouseJobStatus::Cancelled
                || $locked->status === WarehouseJobStatus::ReadyToShip
            ) {
                throw ValidationException::withMessages([
                    'packer_id' => ['Cannot assign packer on a terminal warehouse job.'],
                ]);
            }

            $locked->packer_id = $input['packer_id'] ?? null;
            $locked->save();

            return $locked->fresh(['order.user', 'fulfillment', 'picker', 'packer']) ?? $locked;
        });
    }

    private function syncFulfillmentForWarehouseStatus(WarehouseJob $job, WarehouseJobStatus $status): void
    {
        $job->loadMissing('fulfillment');
        $fulfillment = $job->fulfillment;
        if ($fulfillment === null) {
            return;
        }

        $current = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === WarehouseJobStatus::Picking || $status === WarehouseJobStatus::Picked
            || $status === WarehouseJobStatus::Packing || $status === WarehouseJobStatus::Packed
        ) {
            if ($current === FulfillmentStatus::Pending
                && $current->canTransitionTo(FulfillmentStatus::Processing)
            ) {
                $this->fulfillmentEngine->updateStatus($fulfillment, [
                    'status' => FulfillmentStatus::Processing->value,
                ]);
            }
        }

        if ($status === WarehouseJobStatus::ReadyToShip) {
            $fulfillment = $fulfillment->fresh() ?? $fulfillment;
            $current = $fulfillment->status instanceof FulfillmentStatus
                ? $fulfillment->status
                : FulfillmentStatus::tryFrom((string) $fulfillment->status);

            if ($current === FulfillmentStatus::Pending) {
                $this->fulfillmentEngine->updateStatus($fulfillment, [
                    'status' => FulfillmentStatus::Processing->value,
                ]);
                $fulfillment = $fulfillment->fresh() ?? $fulfillment;
                $current = $fulfillment->status instanceof FulfillmentStatus
                    ? $fulfillment->status
                    : FulfillmentStatus::tryFrom((string) $fulfillment->status);
            }

            if ($current === FulfillmentStatus::Processing
                && $current->canTransitionTo(FulfillmentStatus::ReadyForShipping)
            ) {
                $this->fulfillmentEngine->updateStatus($fulfillment, [
                    'status' => FulfillmentStatus::ReadyForShipping->value,
                ]);
            }
        }

        if ($status === WarehouseJobStatus::Cancelled) {
            if ($current !== null
                && ! $current->isTerminal()
                && $current->canTransitionTo(FulfillmentStatus::Cancelled)
            ) {
                $this->fulfillmentEngine->updateStatus($fulfillment, [
                    'status' => FulfillmentStatus::Cancelled->value,
                ]);
            }
        }
    }
}
