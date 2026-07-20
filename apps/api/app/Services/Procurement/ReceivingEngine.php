<?php

namespace App\Services\Procurement;

use App\Enums\ReceivingStatus;
use App\Events\Procurement\GoodsReceived;
use App\Events\Procurement\InventoryReceived;
use App\Models\Admin;
use App\Models\InventoryLocation;
use App\Models\InventoryLog;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingRecord;
use App\Models\ReceivingRecordItem;
use App\Models\Store;
use App\Services\Inventory\InventoryControlEngine;
use App\Services\Stores\StoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goods receiving → VariantInventory intake via InventoryControlEngine.
 * POs never touch stock directly.
 */
class ReceivingEngine
{
    public function __construct(
        private readonly PurchaseOrderEngine $purchaseOrders,
        private readonly SupplierCostService $costs,
        private readonly InventoryControlEngine $inventory,
        private readonly StoreService $stores,
    ) {}

    /**
     * @param  array{
     *     notes?: string|null,
     *     store_id?: string|null,
     *     inventory_location_id?: string|null,
     *     items: list<array{purchase_order_item_id: string, quantity: int}>
     * }  $data
     */
    public function receive(PurchaseOrder $order, array $data, ?Admin $admin = null): ReceivingRecord
    {
        $lines = $data['items'] ?? [];
        if (! is_array($lines) || $lines === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one receiving line is required.'],
            ]);
        }

        return DB::transaction(function () use ($order, $data, $lines, $admin) {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if (! $locked->status->canReceive()) {
                throw ValidationException::withMessages([
                    'purchase_order' => [
                        "Cannot receive against a purchase order in status [{$locked->status->value}]. Confirm the PO first.",
                    ],
                ]);
            }

            $location = $this->resolveReceiveLocation($locked, $data, $lines);

            $record = ReceivingRecord::query()->create([
                'purchase_order_id' => $locked->id,
                'store_id' => $location?->store_id,
                'inventory_location_id' => $location?->id,
                'received_by' => $admin?->id,
                'status' => ReceivingStatus::Pending,
                'notes' => $data['notes'] ?? null,
            ]);

            $inventoryTouches = [];

            foreach ($lines as $index => $line) {
                $itemId = (string) ($line['purchase_order_item_id'] ?? '');
                $qty = (int) ($line['quantity'] ?? 0);

                if ($qty < 1) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                    ]);
                }

                /** @var PurchaseOrderItem|null $item */
                $item = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $locked->id)
                    ->whereKey($itemId)
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.purchase_order_item_id" => ['Purchase order item not found on this PO.'],
                    ]);
                }

                if ($qty > $item->quantityOutstanding()) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => [
                            "Receiving quantity ({$qty}) exceeds outstanding ordered quantity ({$item->quantityOutstanding()}).",
                        ],
                    ]);
                }

                ReceivingRecordItem::query()->create([
                    'receiving_record_id' => $record->id,
                    'purchase_order_item_id' => $item->id,
                    'quantity_received' => $qty,
                ]);

                $item->quantity_received = (int) $item->quantity_received + $qty;
                $item->save();

                $inventoryTouches[] = ['item' => $item->fresh(['variant.product']), 'qty' => $qty];
            }

            $record->status = ReceivingStatus::Completed;
            $record->received_at = now();
            $record->save();

            foreach ($inventoryTouches as $touch) {
                /** @var PurchaseOrderItem $item */
                $item = $touch['item'];
                $qty = (int) $touch['qty'];
                $this->increaseVariantInventory($item, $qty, $record, $location, $admin);
                $this->costs->recordFromPurchaseItem($locked->supplier_id, $item);
            }

            $this->purchaseOrders->applyReceivingStatus($locked->fresh() ?? $locked);

            $record = $record->fresh([
                'items.purchaseOrderItem.variant.product',
                'purchaseOrder.supplier',
                'receivedByAdmin:id,name,email',
                'store:id,code,name',
                'inventoryLocation:id,code,name',
            ]) ?? $record;

            event(new GoodsReceived($record, $admin));
            event(new InventoryReceived($record, $admin));

            $poFresh = $locked->fresh() ?? $locked;
            if ($poFresh->order_id !== null) {
                try {
                    app(\App\Services\China\ChinaWorkflowEngine::class)
                        ->syncAfterReceiving($poFresh, $admin);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('china.sync_after_receiving_failed', [
                        'purchase_order_id' => $poFresh->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    private function resolveReceiveLocation(PurchaseOrder $order, array $data, array $lines): ?InventoryLocation
    {
        if (! empty($data['inventory_location_id'])) {
            $location = InventoryLocation::query()->find($data['inventory_location_id']);
            if ($location === null) {
                throw ValidationException::withMessages([
                    'inventory_location_id' => ['Inventory location not found.'],
                ]);
            }

            return $location;
        }

        if (! empty($data['store_id'])) {
            $store = Store::query()->find($data['store_id']);
            if ($store === null) {
                throw ValidationException::withMessages(['store_id' => ['Store not found.']]);
            }

            return $this->stores->defaultLocation($store);
        }

        $firstItemId = (string) ($lines[0]['purchase_order_item_id'] ?? '');
        $item = PurchaseOrderItem::query()
            ->with('variant.product')
            ->where('purchase_order_id', $order->id)
            ->whereKey($firstItemId)
            ->first();

        $product = $item?->variant?->product;
        if ($product?->store_id) {
            return $this->inventory->locationForProduct($product);
        }

        // Legacy catalog products without store → MAIN warehouse.
        return null;
    }

    private function increaseVariantInventory(
        PurchaseOrderItem $item,
        int $qty,
        ReceivingRecord $record,
        ?InventoryLocation $location,
        ?Admin $admin,
    ): void {
        /** @var ProductVariant $variant */
        $variant = ProductVariant::query()
            ->whereKey($item->product_variant_id)
            ->lockForUpdate()
            ->firstOrFail();

        $movement = $location
            ? $this->inventory->receiveToLocation(
                $variant,
                $location,
                $qty,
                $admin,
                'procurement_receipt',
                ReceivingRecord::class,
                $record->id,
            )
            : $this->inventory->receiveToWarehouseCode(
                $variant,
                'MAIN',
                $qty,
                $admin,
                'procurement_receipt',
                ReceivingRecord::class,
                $record->id,
            );

        // Compatibility with legacy inventory_logs consumers / tests.
        InventoryLog::query()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity_change' => $qty,
            'quantity_after' => (int) $movement->quantity_after,
            'reason' => 'procurement_receipt',
            'reference_type' => ReceivingRecord::class,
            'reference_id' => $record->id,
            'performed_by_admin_id' => $admin?->id,
        ]);
    }
}
