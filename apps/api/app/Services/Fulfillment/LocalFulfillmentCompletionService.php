<?php

namespace App\Services\Fulfillment;

use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\FulfillmentStrategy;
use App\Enums\NotificationEventType;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Completes Buy From TZ manual-logistics orders after the customer has collected or received goods.
 */
class LocalFulfillmentCompletionService
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly NotificationPlatform $notifications,
    ) {}

    public function complete(Fulfillment $fulfillment, ?Admin $admin = null): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $admin): Fulfillment {
            /** @var Fulfillment $locked */
            $locked = Fulfillment::query()
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->with(['order.user', 'order.deliveryOption', 'warehouseJob'])
                ->firstOrFail();

            $this->assertEligible($locked);

            $fresh = $this->advanceToDelivered($locked, $admin);
            $this->publishCompletionNotification($fresh);

            return $fresh->load(['order.user', 'assignee', 'warehouseJob']);
        });
    }

    private function assertEligible(Fulfillment $fulfillment): void
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::Local) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Only Buy From TZ fulfilments can be completed through this action.'],
            ]);
        }

        $deliveryType = $this->resolveDeliveryType($fulfillment);
        if (! in_array($deliveryType, [DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery], true)) {
            throw ValidationException::withMessages([
                'fulfillment' => ['This fulfilment delivery path does not support local manual completion.'],
            ]);
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === null || $status->isTerminal()) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Fulfilment is already complete or cancelled.'],
            ]);
        }

        if ($status !== FulfillmentStatus::ReadyForShipping) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Fulfilment must be order-ready before it can be marked completed.'],
            ]);
        }

        $warehouseStatus = $fulfillment->warehouseJob?->status;
        $warehouse = $warehouseStatus instanceof WarehouseJobStatus
            ? $warehouseStatus
            : WarehouseJobStatus::tryFrom((string) ($warehouseStatus ?? ''));

        if ($warehouse !== WarehouseJobStatus::ReadyToShip) {
            throw ValidationException::withMessages([
                'warehouse' => ['Warehouse preparation must be complete before marking the order completed.'],
            ]);
        }
    }

    private function advanceToDelivered(Fulfillment $fulfillment, ?Admin $admin): Fulfillment
    {
        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === null || $status->isTerminal()) {
            return $fulfillment;
        }

        $context = new FulfillmentStatusUpdateContext(
            source: FulfillmentStatusHistorySource::Admin,
            admin: $admin,
        );

        while ($status !== FulfillmentStatus::Delivered) {
            $next = match ($status) {
                FulfillmentStatus::ReadyForShipping => FulfillmentStatus::Shipped,
                FulfillmentStatus::Shipped => FulfillmentStatus::Delivered,
                default => null,
            };

            if ($next === null || ! $status->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'fulfillment' => ["Cannot advance fulfilment from [{$status->value}] to completed."],
                ]);
            }

            $notes = match ($next) {
                FulfillmentStatus::Shipped => 'Buy From TZ order handed to customer',
                FulfillmentStatus::Delivered => 'Buy From TZ order marked completed by admin',
                default => 'Buy From TZ fulfilment advanced',
            };

            $fulfillment = $this->fulfillmentEngine->updateStatus(
                $fulfillment,
                ['status' => $next->value, 'notes' => $notes],
                new FulfillmentStatusUpdateContext(
                    source: $context->source,
                    admin: $context->admin,
                    notes: $notes,
                ),
            );

            $status = $next;
        }

        return $fulfillment;
    }

    private function publishCompletionNotification(Fulfillment $fulfillment): void
    {
        $fulfillment->loadMissing(['order.user', 'order.deliveryOption']);
        $user = $fulfillment->order?->user;
        if ($user === null) {
            return;
        }

        $deliveryType = $this->resolveDeliveryType($fulfillment);
        $eventType = match ($deliveryType) {
            DeliveryType::SelfPickup => NotificationEventType::LocalOrderCompletedPickup,
            DeliveryType::NegotiatedDelivery => NotificationEventType::LocalOrderCompletedDeliveryArrangement,
            default => null,
        };

        if ($eventType === null) {
            return;
        }

        try {
            $this->notifications->notifyCustomer($eventType, $user, [
                'customer_name' => $user->name,
                'order_number' => $fulfillment->order?->order_number,
                'order_id' => $fulfillment->order_id,
                'fulfillment_id' => $fulfillment->id,
            ], idempotencyKey: 'local-complete:'.$fulfillment->id);
        } catch (\Throwable $e) {
            Log::warning('notification.local_completion_publish_failed', [
                'fulfillment_id' => $fulfillment->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveDeliveryType(Fulfillment $fulfillment): ?DeliveryType
    {
        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;

        if ($deliveryType instanceof DeliveryType) {
            return $deliveryType;
        }

        return DeliveryType::tryFrom((string) ($deliveryType ?? ''));
    }
}
