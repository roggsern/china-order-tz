<?php

namespace App\Services\Fulfillment;

use App\Enums\ChinaExportReadiness;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStrategy;
use App\Models\ChinaWorkflowRecord;
use App\Models\CustomerAgentPickup;
use App\Models\Fulfillment;
use App\Models\PurchaseOrder;
use App\Services\Orders\CustomerOrderProgressResolver;

class FulfillmentOperationalReadModelBuilder
{
    public function __construct(
        private readonly CustomerOrderProgressResolver $progressResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Fulfillment $fulfillment): array
    {
        $fulfillment->loadMissing([
            'assignee',
            'order.user',
            'order.items',
            'order.deliveryOption',
            'order.payments',
            'warehouseJob.picker',
            'warehouseJob.packer',
            'shipment',
            'statusHistories.changedByAdmin',
        ]);

        $order = $fulfillment->order;
        $warehouseJob = $fulfillment->warehouseJob;
        $shipment = $fulfillment->shipment;
        $progress = $order !== null
            ? $this->progressResolver->resolve($order)
            : null;

        $payload = [
            'fulfillment' => [
                'id' => $fulfillment->id,
                'status' => $fulfillment->status?->value ?? (string) $fulfillment->status,
                'status_label' => $fulfillment->status?->label() ?? null,
                'strategy' => $fulfillment->strategy?->value ?? (string) $fulfillment->strategy,
                'strategy_label' => $fulfillment->strategy?->label() ?? null,
                'assigned_to' => $fulfillment->assigned_to,
                'assignee' => $fulfillment->assignee ? [
                    'id' => $fulfillment->assignee->id,
                    'name' => $fulfillment->assignee->name,
                    'email' => $fulfillment->assignee->email,
                ] : null,
                'started_at' => $fulfillment->started_at,
                'completed_at' => $fulfillment->completed_at,
                'notes' => $fulfillment->notes,
                'created_at' => $fulfillment->created_at,
                'updated_at' => $fulfillment->updated_at,
            ],
            'order' => $order !== null ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status?->value ?? (string) $order->status,
                'source' => $order->resolveSource(),
                'journey' => $order->resolveSource(),
                'customer' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone ?? null,
                ] : null,
                'delivery_type' => $order->deliveryOption?->delivery_type?->value
                    ?? ($order->deliveryOption?->delivery_type !== null ? (string) $order->deliveryOption->delivery_type : null),
                'last_mile_receiving_method' => $order->deliveryOption?->last_mile_receiving_method instanceof \App\Enums\LastMileReceivingMethod
                    ? $order->deliveryOption->last_mile_receiving_method->value
                    : ($order->deliveryOption?->last_mile_receiving_method !== null
                        ? (string) $order->deliveryOption->last_mile_receiving_method
                        : null),
                'product' => $this->buildProductSummary($order),
            ] : null,
            'status_history' => $fulfillment->statusHistories
                ->sortBy('created_at')
                ->values()
                ->map(fn ($history) => [
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'source' => $history->source?->value ?? (string) $history->source,
                    'changed_by' => $history->changed_by,
                    'changed_by_admin' => $history->relationLoaded('changedByAdmin') && $history->changedByAdmin
                        ? [
                            'id' => $history->changedByAdmin->id,
                            'name' => $history->changedByAdmin->name,
                        ]
                        : null,
                    'notes' => $history->notes,
                    'created_at' => $history->created_at,
                ])
                ->all(),
            'warehouse' => $warehouseJob !== null ? [
                'id' => $warehouseJob->id,
                'job_number' => $warehouseJob->job_number,
                'status' => $warehouseJob->status?->value ?? (string) $warehouseJob->status,
                'status_label' => $warehouseJob->status?->label() ?? null,
                'picker' => $warehouseJob->picker ? [
                    'id' => $warehouseJob->picker->id,
                    'name' => $warehouseJob->picker->name,
                ] : null,
                'packer' => $warehouseJob->packer ? [
                    'id' => $warehouseJob->packer->id,
                    'name' => $warehouseJob->packer->name,
                ] : null,
                'picked_at' => $warehouseJob->picked_at,
                'packed_at' => $warehouseJob->packed_at,
                'ready_at' => $warehouseJob->ready_at,
            ] : null,
            'shipment' => $shipment !== null ? [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->status?->value ?? (string) $shipment->status,
                'status_label' => $shipment->status?->label() ?? null,
                'carrier' => $shipment->carrier_name ?? $shipment->carrier,
                'tracking_number' => $shipment->tracking_reference ?? $shipment->tracking_number,
                'transport_mode' => $shipment->transport_mode?->value ?? $shipment->transport_mode,
                'booked_at' => $shipment->booked_at,
                'shipped_at' => $shipment->shipped_at,
                'arrived_at' => $shipment->arrived_at,
                'delivered_at' => $shipment->delivered_at,
            ] : null,
            'china' => null,
            'customer_agent' => null,
            'customer_progress' => $progress !== null ? [
                'current_key' => $progress['current_key'],
                'current_label' => $progress['current_label'],
                'steps' => $progress['steps'],
            ] : null,
        ];

        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy === FulfillmentStrategy::China && $order !== null) {
            $payload['china'] = $this->buildChinaSection($fulfillment, $order);
        }

        if ($order !== null) {
            $payload['customer_agent'] = $this->buildCustomerAgentSection($order);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCustomerAgentSection(\App\Models\Order $order): ?array
    {
        $delivery = $order->deliveryOption;
        $type = $delivery?->delivery_type instanceof DeliveryType
            ? $delivery->delivery_type
            : DeliveryType::tryFrom((string) ($delivery?->delivery_type ?? ''));

        if ($type !== DeliveryType::CustomerAgent || $delivery === null) {
            return null;
        }

        $pickup = CustomerAgentPickup::query()->where('order_id', $order->id)->first();

        return [
            'delivery_method_label' => 'Customer Agent Delivery',
            'agent_name' => $delivery->agent_name,
            'agent_phone' => $delivery->agent_phone ?: $delivery->agent_contact,
            'agent_contact' => $delivery->agent_contact,
            'agent_company' => $delivery->agent_company,
            'agent_email' => $delivery->agent_email,
            'pickup_reference' => $pickup?->pickup_reference ?? $delivery->pickup_reference,
            'authorization_status' => $pickup?->authorization_status?->value
                ?? ($pickup?->authorization_status !== null ? (string) $pickup->authorization_status : null),
            'release_status' => $pickup?->release_status?->value
                ?? ($pickup?->release_status !== null ? (string) $pickup->release_status : null),
            'pickup_status' => $pickup?->pickup_status?->value
                ?? ($pickup?->pickup_status !== null ? (string) $pickup->pickup_status : null),
            'handover_completed_at' => $pickup?->handover_completed_at,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array<string, mixed>|null
     */
    private function buildProductSummary(\App\Models\Order $order): ?array
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->orderBy('created_at')->get();

        if ($items->isEmpty()) {
            return null;
        }

        $primary = $items->first();
        $variantLabel = $primary->variant_name_snapshot
            ?? $primary->variant_name
            ?? null;

        if ($variantLabel === null && is_array($primary->attributes_snapshot) && $primary->attributes_snapshot !== []) {
            $parts = collect($primary->attributes_snapshot)
                ->map(fn ($value, $key) => is_scalar($value) ? trim((string) $value) : null)
                ->filter()
                ->values()
                ->all();
            if ($parts !== []) {
                $variantLabel = implode(' • ', $parts);
            }
        }

        return [
            'name' => $primary->product_name_snapshot ?? $primary->product_name ?? 'Product',
            'variant_label' => $variantLabel,
            'quantity' => (int) ($primary->quantity ?? 1),
            'image_url' => $primary->product_image_snapshot
                ?? $primary->image_snapshot
                ?? null,
            'additional_item_count' => max(0, $items->count() - 1),
        ];
    }

    private function buildChinaSection(Fulfillment $fulfillment, \App\Models\Order $order): ?array
    {
        $record = ChinaWorkflowRecord::query()
            ->where('order_id', $order->id)
            ->first();

        $purchaseOrders = PurchaseOrder::query()
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get();

        if ($record === null && $purchaseOrders->isEmpty()) {
            return null;
        }

        $procurementStatuses = $purchaseOrders
            ->map(fn (PurchaseOrder $po) => [
                'purchase_number' => $po->purchase_number,
                'status' => $po->status?->value ?? (string) $po->status,
                'status_label' => $po->status?->label() ?? null,
                'supplier_response' => $po->supplier_response,
            ])
            ->values()
            ->all();

        return [
            'stage' => $record?->stage?->value ?? null,
            'stage_label' => $record?->stage?->label() ?? null,
            'qc_status' => $record?->qc_status?->value ?? null,
            'qc_status_label' => $record?->qc_status?->label() ?? null,
            'export_readiness' => $record !== null
                ? $record->exportReadiness()->value
                : ChinaExportReadiness::NotReady->value,
            'export_ready_at' => $record?->export_ready_at,
            'procurement' => $procurementStatuses,
        ];
    }
}
