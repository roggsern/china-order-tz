<?php

namespace App\Listeners\China;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Events\Audit\ChinaPurchaseCompletedAudit;
use App\Events\Audit\ChinaPurchaseRequirementCreatedAudit;
use App\Events\Audit\PaymentConfirmed;
use App\Models\Admin;
use App\Models\Order;
use App\Services\China\Procurement\ChinaProcurementBoardEngine;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\Log;

class HandleChinaProcurementLifecycle
{
    public function __construct(
        private readonly ChinaProcurementBoardEngine $procurement,
        private readonly NotificationPlatform $notifications,
    ) {}

    public function onPaymentConfirmed(PaymentConfirmed $event): void
    {
        if ($event->subjectClass !== Order::class || $event->subjectId() === null) {
            return;
        }

        $order = Order::query()->with(['items.product.commerceChannel', 'items.variant'])->find($event->subjectId());
        if ($order === null) {
            return;
        }

        $this->procurement->recordPaidOrderDemand($order);

        $this->notifyAdmins(
            NotificationEventType::PurchaseRequirementReady,
            [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
            ],
            'China purchase requirement ready',
            'purchase-requirement:'.$order->id,
        );
    }

    public function onPurchaseCompleted(ChinaPurchaseCompletedAudit $event): void
    {
        $this->notifyAdmins(
            NotificationEventType::ChinaPurchaseCompleted,
            [
                'requirement_id' => $event->subjectId(),
            ],
            'China purchase completed',
            'china-purchase-completed:'.$event->subjectId(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyAdmins(
        NotificationEventType $type,
        array $data,
        string $title,
        ?string $idempotencyKey = null,
    ): void {
        try {
            $admins = Admin::query()->where('is_active', true)->limit(25)->get();
            foreach ($admins as $admin) {
                $this->notifications->notifyAdmin(
                    $type,
                    $admin,
                    $data,
                    [NotificationChannel::InApp],
                    $title,
                    $idempotencyKey,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('china_procurement.notify_admins_failed', [
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
