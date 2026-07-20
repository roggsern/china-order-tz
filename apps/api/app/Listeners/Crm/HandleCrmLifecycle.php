<?php

namespace App\Listeners\Crm;

use App\Enums\CustomerTimelineEventType;
use App\Enums\OrderStatus;
use App\Enums\TrackingEventType;
use App\Events\Audit\CustomerNoteAddedAudit;
use App\Events\Audit\CustomerProfileCreatedAudit;
use App\Events\Audit\CustomerProfileUpdatedAudit;
use App\Events\Audit\CustomerStatusChangedAudit;
use App\Events\Audit\CustomerTagAssignedAudit;
use App\Events\Audit\CustomerTagRemovedAudit;
use App\Events\Audit\PaymentConfirmed;
use App\Events\Audit\ShipmentCreated as ShipmentCreatedAudit;
use App\Events\Audit\TrackingEventAdded;
use App\Events\Commerce\CommerceOrderCreated;
use App\Events\CostProfit\ProfitCalculated;
use App\Events\Crm\CustomerBlocked;
use App\Events\Crm\CustomerMetricsUpdated;
use App\Events\Crm\CustomerNoteAdded;
use App\Events\Crm\CustomerProfileCreated;
use App\Events\Crm\CustomerProfileUpdated;
use App\Events\Crm\CustomerStatusChanged;
use App\Events\Crm\CustomerTagAssigned;
use App\Events\Crm\CustomerTagRemoved;
use App\Events\Crm\CustomerUnblocked;
use App\Events\Returns\RefundCompleted;
use App\Events\Returns\ReturnRequested;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Services\Crm\CustomerMetricsService;
use App\Services\Crm\CustomerProfileService;
use App\Services\Crm\CustomerTimelineService;
use Illuminate\Support\Facades\Log;

class HandleCrmLifecycle
{
    public function __construct(
        private readonly CustomerProfileService $profiles,
        private readonly CustomerMetricsService $metrics,
        private readonly CustomerTimelineService $timeline,
    ) {}

    public function onProfileCreated(CustomerProfileCreated $event): void
    {
        event(CustomerProfileCreatedAudit::fromProfile($event->profile, $event->admin));
    }

    public function onProfileUpdated(CustomerProfileUpdated $event): void
    {
        event(CustomerProfileUpdatedAudit::fromProfile($event->profile, $event->before, $event->admin));
    }

    public function onStatusChanged(CustomerStatusChanged $event): void
    {
        event(CustomerStatusChangedAudit::fromChange(
            $event->profile,
            $event->from,
            $event->to,
            $event->admin,
            $event->reason,
        ));
    }

    public function onBlocked(CustomerBlocked $event): void
    {
        // Audited via CustomerStatusChanged.
    }

    public function onUnblocked(CustomerUnblocked $event): void
    {
        // Audited via CustomerStatusChanged.
    }

    public function onTagAssigned(CustomerTagAssigned $event): void
    {
        event(CustomerTagAssignedAudit::fromAssignment($event->profile, $event->tag, $event->admin));
    }

    public function onTagRemoved(CustomerTagRemoved $event): void
    {
        event(CustomerTagRemovedAudit::fromRemoval($event->profile, $event->tag, $event->admin));
    }

    public function onNoteAdded(CustomerNoteAdded $event): void
    {
        event(CustomerNoteAddedAudit::fromNote($event->note, $event->admin));
    }

    public function onMetricsUpdated(CustomerMetricsUpdated $event): void
    {
        // Projection update — no customer notification.
    }

    public function onCommerceOrderCreated(CommerceOrderCreated $event): void
    {
        $this->touchOrder($event->order, CustomerTimelineEventType::OrderCreated, 'Order created', sprintf(
            'Order %s placed (%s %s)',
            $event->order->order_number,
            $event->order->total,
            $event->order->currency,
        ));
    }

    public function onPaymentConfirmed(PaymentConfirmed $event): void
    {
        $order = Order::query()->find($event->subjectId());
        if ($order === null) {
            return;
        }

        $this->touchOrder($order, CustomerTimelineEventType::PaymentCompleted, 'Payment completed', sprintf(
            'Payment confirmed for order %s',
            $order->order_number,
        ));
    }

    public function onShipmentCreated(ShipmentCreatedAudit $event): void
    {
        $shipment = Shipment::query()->with('order')->find($event->subjectId());
        $order = $shipment?->order;
        if ($order === null) {
            return;
        }

        $this->touchOrder($order, CustomerTimelineEventType::ShipmentCreated, 'Shipment created', sprintf(
            'Shipment created for order %s',
            $order->order_number,
        ), Shipment::class, $shipment->id);
    }

    public function onTrackingEventAdded(TrackingEventAdded $event): void
    {
        $tracking = ShipmentTrackingEvent::query()->with('shipment.order')->find($event->subjectId());
        if ($tracking === null || $tracking->shipment?->order === null) {
            return;
        }

        $type = $tracking->event_type instanceof TrackingEventType
            ? $tracking->event_type
            : TrackingEventType::tryFrom((string) $tracking->event_type);

        if ($type !== TrackingEventType::Delivered) {
            return;
        }

        $order = $tracking->shipment->order;
        $this->touchOrder(
            $order,
            CustomerTimelineEventType::ShipmentDelivered,
            'Shipment delivered',
            sprintf('Order %s delivered', $order->order_number),
            ShipmentTrackingEvent::class,
            $tracking->id,
        );

        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed], true)) {
            $this->touchOrder(
                $order,
                CustomerTimelineEventType::OrderCompleted,
                'Order completed',
                sprintf('Order %s completed', $order->order_number),
                Order::class,
                $order->id,
                recalculate: false,
            );
            $this->metrics->recalculateForUserId($order->user_id);
        }
    }

    public function onReturnRequested(ReturnRequested $event): void
    {
        $return = $event->returnRequest->loadMissing('order');
        $userId = $return->customer_id ?? $return->order?->user_id;
        $profile = $this->profileForUserId($userId);
        if ($profile === null) {
            return;
        }

        $this->timeline->append(
            $profile,
            CustomerTimelineEventType::ReturnRequested,
            'Return requested',
            'Return requested for order '.($return->order?->order_number ?? $return->order_id),
            $return::class,
            $return->id,
        );
        $this->metrics->recalculate($profile);
    }

    public function onRefundCompleted(RefundCompleted $event): void
    {
        $refund = $event->refund->loadMissing('order');
        $userId = $refund->order?->user_id;
        $profile = $this->profileForUserId($userId);
        if ($profile === null) {
            return;
        }

        $this->timeline->append(
            $profile,
            CustomerTimelineEventType::RefundCompleted,
            'Refund completed',
            sprintf('Refund %s %s completed', $refund->amount, $refund->currency ?? 'TZS'),
            $refund::class,
            $refund->id,
        );
        $this->metrics->recalculate($profile);
    }

    public function onProfitCalculated(ProfitCalculated $event): void
    {
        $record = $event->profitRecord->loadMissing('order');
        $this->metrics->recalculateForUserId($record->order?->user_id);
    }

    private function touchOrder(
        Order $order,
        CustomerTimelineEventType $type,
        string $title,
        string $description,
        ?string $subjectType = null,
        ?string $subjectId = null,
        bool $recalculate = true,
    ): void {
        try {
            $userId = $order->user_id;
            if ($userId === null) {
                return;
            }

            $user = $order->user;
            if ($user === null) {
                $user = \App\Models\User::query()->find($userId);
            }
            if ($user === null || ! $user->hasRole('customer')) {
                return;
            }

            $profile = $this->profiles->ensureForUser($user);
            $this->timeline->append(
                $profile,
                $type,
                $title,
                $description,
                $subjectType ?? Order::class,
                $subjectId ?? $order->id,
                [
                    'order_number' => $order->order_number,
                    'order_status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                ],
            );

            if ($recalculate) {
                $this->metrics->recalculate($profile);
            }
        } catch (\Throwable $e) {
            Log::warning('crm.touch_order_failed', [
                'order_id' => $order->id,
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function profileForUserId(?string $userId): ?CustomerProfile
    {
        if ($userId === null) {
            return null;
        }

        $user = \App\Models\User::query()->find($userId);
        if ($user === null || ! $user->hasRole('customer')) {
            return null;
        }

        return $this->profiles->ensureForUser($user);
    }
}
