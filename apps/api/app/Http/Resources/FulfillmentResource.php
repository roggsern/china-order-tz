<?php

namespace App\Http\Resources;

use App\Enums\ChinaWorkflowStage;
use App\Enums\FulfillmentStrategy;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\SupplierPoResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Fulfillment */
class FulfillmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'strategy' => $this->strategy?->value ?? $this->strategy,
            'strategy_label' => $this->strategy?->label(),
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'notes' => $this->notes,
            'warehouse_status' => $this->whenLoaded('warehouseJob', function () {
                $status = $this->warehouseJob?->status;

                return $status instanceof \App\Enums\WarehouseJobStatus
                    ? $status->value
                    : (string) ($status ?? '');
            }),
            'shipment_status' => $this->whenLoaded('shipment', function () {
                $status = $this->shipment?->status;

                return $status instanceof ShipmentLifecycleStatus
                    ? $status->value
                    : (string) ($status ?? '');
            }),
            'shipment_arrived_at' => $this->whenLoaded('shipment', fn () => $this->shipment?->arrived_at),
            'china' => $this->when($this->isChinaStrategy(), fn () => $this->buildChinaBulkSummary()),
            'order' => $this->whenLoaded('order', function () {
                $product = null;
                if ($this->order->relationLoaded('items') && $this->order->items->isNotEmpty()) {
                    $primary = $this->order->items->first();
                    $variantLabel = $primary->variant_name_snapshot
                        ?? $primary->variant_name
                        ?? null;

                    if ($variantLabel === null && is_array($primary->attributes_snapshot) && $primary->attributes_snapshot !== []) {
                        $parts = collect($primary->attributes_snapshot)
                            ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
                            ->filter()
                            ->values()
                            ->all();
                        if ($parts !== []) {
                            $variantLabel = implode(' • ', $parts);
                        }
                    }

                    $product = [
                        'name' => $primary->product_name_snapshot ?? $primary->product_name ?? 'Product',
                        'variant_label' => $variantLabel,
                        'quantity' => (int) ($primary->quantity ?? 1),
                        'image_url' => $primary->product_image_snapshot
                            ?? $primary->image_snapshot
                            ?? null,
                        'additional_item_count' => max(0, $this->order->items->count() - 1),
                    ];
                }

                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'status' => $this->order->status?->value ?? $this->order->status,
                    'source' => $this->order->resolveSource(),
                    'journey' => $this->order->resolveSource(),
                    'total' => $this->order->total,
                    'currency' => $this->order->currency,
                    'paid_at' => $this->order->paid_at,
                    'delivery_type' => $this->order->relationLoaded('deliveryOption') && $this->order->deliveryOption
                        ? ($this->order->deliveryOption->delivery_type?->value ?? $this->order->deliveryOption->delivery_type)
                        : null,
                    'last_mile_receiving_method' => $this->order->relationLoaded('deliveryOption') && $this->order->deliveryOption
                        ? ($this->order->deliveryOption->last_mile_receiving_method?->value
                            ?? $this->order->deliveryOption->last_mile_receiving_method)
                        : null,
                    'product' => $product,
                    'customer' => $this->order->relationLoaded('user') && $this->order->user
                        ? [
                            'id' => $this->order->user->id,
                            'name' => $this->order->user->name,
                            'email' => $this->order->user->email,
                            'phone' => $this->order->user->phone ?? null,
                        ]
                        : null,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function isChinaStrategy(): bool
    {
        $strategy = $this->strategy instanceof FulfillmentStrategy
            ? $this->strategy
            : FulfillmentStrategy::tryFrom((string) ($this->strategy ?? ''));

        return $strategy === FulfillmentStrategy::China;
    }

    /**
     * @return array{
     *     stage: string|null,
     *     qc_status: string|null,
     *     export_ready: bool,
     *     has_supplier_purchase: bool,
     *     purchase_receivable: bool,
     *     supplier_purchase_state: string
     * }
     */
    private function buildChinaBulkSummary(): array
    {
        $record = $this->relationLoaded('chinaWorkflowRecord') ? $this->chinaWorkflowRecord : null;
        $metadata = is_array($record?->metadata) ? $record->metadata : [];
        $hasPoIds = ! empty($metadata['purchase_order_ids']);
        $hasPurchase = (bool) ($this->has_active_purchase_orders ?? false) || $hasPoIds;

        return [
            'stage' => $record?->stage instanceof ChinaWorkflowStage
                ? $record->stage->value
                : (is_string($record?->stage) ? $record->stage : null),
            'qc_status' => $record?->qc_status?->value ?? (is_string($record?->qc_status) ? $record->qc_status : null),
            'export_ready' => $record?->isAuthoritativelyExportReady() ?? false,
            'has_supplier_purchase' => $hasPurchase,
            'purchase_receivable' => $this->resolvePurchaseReceivable($hasPurchase),
            'supplier_purchase_state' => $this->resolveSupplierPurchaseState($record, $hasPurchase),
        ];
    }

    private function resolvePurchaseReceivable(bool $hasPurchase): bool
    {
        if (! $hasPurchase || ! $this->relationLoaded('purchaseOrders')) {
            return false;
        }

        foreach ($this->purchaseOrders as $purchaseOrder) {
            $status = $purchaseOrder->status instanceof PurchaseOrderStatus
                ? $purchaseOrder->status
                : PurchaseOrderStatus::tryFrom((string) ($purchaseOrder->status ?? ''));

            if ($status === null || ! $status->canReceive()) {
                continue;
            }

            $response = $purchaseOrder->supplier_response instanceof SupplierPoResponse
                ? $purchaseOrder->supplier_response
                : SupplierPoResponse::tryFrom((string) ($purchaseOrder->supplier_response ?? ''));

            if (! in_array($response, [SupplierPoResponse::Accepted, SupplierPoResponse::PartiallyAccepted], true)) {
                continue;
            }

            $items = $purchaseOrder->relationLoaded('items')
                ? $purchaseOrder->items
                : $purchaseOrder->items()->get();

            foreach ($items as $item) {
                if ($item->quantityOutstanding() > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveSupplierPurchaseState(?\App\Models\ChinaWorkflowRecord $record, bool $hasPurchase): string
    {
        if (! $hasPurchase) {
            $stage = $record?->stage instanceof ChinaWorkflowStage
                ? $record->stage
                : ChinaWorkflowStage::tryFrom((string) ($record?->stage ?? ''));

            if ($stage === null || $stage === ChinaWorkflowStage::AwaitingProcurement) {
                return 'none';
            }

            return 'missing';
        }

        $stage = $record?->stage instanceof ChinaWorkflowStage
            ? $record->stage
            : ChinaWorkflowStage::tryFrom((string) ($record?->stage ?? ''));

        if ($stage === null || $stage === ChinaWorkflowStage::ProcurementInProgress) {
            return 'active';
        }

        return 'established';
    }
}
