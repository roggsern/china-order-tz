<?php

namespace App\Listeners\Returns;

use App\Events\Audit\RefundCompletedAudit;
use App\Events\Audit\RefundCreatedAudit;
use App\Events\Audit\ReturnApprovedAudit;
use App\Events\Audit\ReturnCompletedAudit;
use App\Events\Audit\ReturnRejectedAudit;
use App\Events\Audit\ReturnRequestedAudit;
use App\Events\Returns\RefundCompleted;
use App\Events\Returns\RefundCreated;
use App\Events\Returns\ReturnApproved;
use App\Events\Returns\ReturnCompleted;
use App\Events\Returns\ReturnRejected;
use App\Events\Returns\ReturnRequested;
use App\Enums\NotificationEventType;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Illuminate\Support\Facades\Log;

/**
 * Bridges return/refund domain events → Notification Platform + Audit Platform.
 * Named record* (not handle) to avoid Laravel event discovery double-registration.
 */
class HandleReturnLifecycle
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
        private readonly OrderLifecycleEngine $lifecycle,
    ) {}

    public function onRequested(ReturnRequested $event): void
    {
        $return = $event->returnRequest->loadMissing(['order', 'customer']);
        event(ReturnRequestedAudit::fromReturn($return));

        if ($return->customer !== null) {
            $this->notify($return->customer, NotificationEventType::ReturnRequested, $return);
        }
    }

    public function onApproved(ReturnApproved $event): void
    {
        $return = $event->returnRequest->loadMissing(['order', 'customer']);
        event(ReturnApprovedAudit::fromReturn($return, $event->admin));

        if ($return->customer !== null) {
            $this->notify($return->customer, NotificationEventType::ReturnApproved, $return);
        }
    }

    public function onRejected(ReturnRejected $event): void
    {
        $return = $event->returnRequest->loadMissing(['order', 'customer']);
        event(ReturnRejectedAudit::fromReturn($return, $event->admin));

        if ($return->customer !== null) {
            $this->notify($return->customer, NotificationEventType::ReturnRejected, $return);
        }
    }

    public function onCompleted(ReturnCompleted $event): void
    {
        $return = $event->returnRequest->loadMissing(['order', 'customer']);
        event(ReturnCompletedAudit::fromReturn($return, $event->admin));
    }

    public function onRefundCreated(RefundCreated $event): void
    {
        event(RefundCreatedAudit::fromRefund($event->refund, $event->admin));
    }

    public function onRefundCompleted(RefundCompleted $event): void
    {
        $refund = $event->refund->loadMissing(['returnRequest.customer', 'order']);
        event(RefundCompletedAudit::fromRefund($refund, $event->admin));

        if ($refund->order !== null) {
            try {
                $this->lifecycle->markRefunded(
                    $refund->order,
                    new OrderLifecycleContext(
                        source: 'refund',
                        reason: 'Refund transaction completed',
                        admin: $event->admin,
                        idempotencyKey: 'refund-completed:'.$refund->id,
                        metadata: [
                            'refund_transaction_id' => $refund->id,
                            'return_request_id' => $refund->return_request_id,
                        ],
                    ),
                );
            } catch (\Throwable $e) {
                Log::warning('lifecycle.mark_refunded_failed', [
                    'order_id' => $refund->order_id,
                    'refund_id' => $refund->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $customer = $refund->returnRequest?->customer ?? $refund->order?->user;
        if ($customer !== null) {
            try {
                $this->notifications->notifyCustomer(
                    NotificationEventType::RefundCompleted,
                    $customer,
                    [
                        'customer_name' => $customer->name,
                        'order_number' => $refund->order?->order_number,
                        'order_id' => $refund->order_id,
                        'refund_amount' => (string) $refund->amount,
                        'currency' => $refund->currency,
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('returns.notify_refund_completed_failed', [
                    'refund_id' => $refund->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function notify(
        \App\Models\User $customer,
        NotificationEventType $type,
        \App\Models\ReturnRequest $return,
    ): void {
        try {
            $this->notifications->notifyCustomer($type, $customer, [
                'customer_name' => $customer->name,
                'order_number' => $return->order?->order_number,
                'order_id' => $return->order_id,
                'return_id' => $return->id,
                'reason' => $return->reason,
            ]);
        } catch (\Throwable $e) {
            Log::warning('returns.notify_failed', [
                'return_id' => $return->id,
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
