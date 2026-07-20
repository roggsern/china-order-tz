<?php

namespace App\Listeners\Procurement;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Events\Audit\GoodsReceivedAudit;
use App\Events\Audit\InventoryReceivedAudit;
use App\Events\Audit\PurchaseOrderConfirmedAudit;
use App\Events\Audit\PurchaseOrderCreatedAudit;
use App\Events\Audit\PurchaseOrderStatusChangedAudit;
use App\Events\Audit\SupplierCreatedAudit;
use App\Events\Audit\SupplierUpdatedAudit;
use App\Events\Procurement\GoodsReceived;
use App\Events\Procurement\InventoryReceived;
use App\Events\Procurement\PurchaseOrderConfirmed;
use App\Events\Procurement\PurchaseOrderCreated;
use App\Events\Procurement\PurchaseOrderStatusChanged;
use App\Events\Procurement\SupplierCreated;
use App\Events\Procurement\SupplierUpdated;
use App\Models\Admin;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\Log;

class HandleProcurementLifecycle
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    public function onSupplierCreated(SupplierCreated $event): void
    {
        event(SupplierCreatedAudit::fromSupplier($event->supplier, $event->admin));
    }

    public function onSupplierUpdated(SupplierUpdated $event): void
    {
        event(SupplierUpdatedAudit::fromChanges(
            $event->supplier,
            $event->before,
            $event->after,
            $event->admin,
        ));
    }

    public function onPurchaseOrderCreated(PurchaseOrderCreated $event): void
    {
        event(PurchaseOrderCreatedAudit::fromOrder($event->purchaseOrder, $event->admin));
    }

    public function onPurchaseOrderConfirmed(PurchaseOrderConfirmed $event): void
    {
        event(PurchaseOrderConfirmedAudit::fromOrder($event->purchaseOrder, $event->admin));
        $this->notifyAdmins(
            NotificationEventType::PurchaseOrderConfirmed,
            [
                'purchase_number' => $event->purchaseOrder->purchase_number,
                'purchase_order_id' => $event->purchaseOrder->id,
                'supplier_name' => $event->purchaseOrder->supplier?->name
                    ?? $event->purchaseOrder->loadMissing('supplier')->supplier?->name,
                'status' => 'confirmed',
            ],
            'Purchase order confirmed',
        );
    }

    public function onPurchaseOrderStatusChanged(PurchaseOrderStatusChanged $event): void
    {
        event(PurchaseOrderStatusChangedAudit::fromTransition(
            $event->purchaseOrder,
            $event->from,
            $event->to,
            $event->admin,
        ));
    }

    public function onGoodsReceived(GoodsReceived $event): void
    {
        $record = $event->receivingRecord->loadMissing('purchaseOrder.supplier');
        event(GoodsReceivedAudit::fromReceiving($record, $event->admin));
        $this->notifyAdmins(
            NotificationEventType::GoodsReceived,
            [
                'purchase_number' => $record->purchaseOrder?->purchase_number,
                'purchase_order_id' => $record->purchase_order_id,
                'receiving_id' => $record->id,
                'supplier_name' => $record->purchaseOrder?->supplier?->name,
            ],
            'Goods received',
        );
    }

    public function onInventoryReceived(InventoryReceived $event): void
    {
        event(InventoryReceivedAudit::fromReceiving($event->receivingRecord, $event->admin));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyAdmins(NotificationEventType $type, array $data, string $title): void
    {
        try {
            $admins = Admin::query()->where('is_active', true)->limit(25)->get();
            foreach ($admins as $admin) {
                $this->notifications->notifyAdmin(
                    $type,
                    $admin,
                    $data,
                    [NotificationChannel::InApp],
                    $title,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('procurement.notify_admins_failed', [
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
