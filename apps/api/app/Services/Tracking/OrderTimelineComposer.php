<?php

namespace App\Services\Tracking;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\TimelineSourceModule;
use App\Enums\TimelineVisibility;
use App\Enums\WarehouseJobStatus;
use App\Models\ChinaWorkflowHistory;
use App\Models\CustomerAgentPickupHistory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderTrackingEvent;
use App\Models\ShipmentStatusHistory;
use App\Models\ShipmentTrackingEvent;
use Illuminate\Support\Collection;

/**
 * Projection-only timeline composer.
 * NEVER writes business state. NEVER sends notifications.
 * Consumes authoritative module histories and produces a unified read model.
 */
class OrderTimelineComposer
{
    /**
     * @return list<array<string, mixed>>
     */
    public function compose(Order $order, TimelineVisibility $visibility = TimelineVisibility::Customer): array
    {
        $order->loadMissing([
            'shipmentStatusHistories',
            'fulfillment.shipment.trackingEvents',
            'fulfillment.warehouseJob',
            'deliveryOption',
            'warehouseJob',
        ]);

        $entries = collect()
            ->merge($this->fromOrderLifecycle($order))
            ->merge($this->fromShipmentStatusHistory($order))
            ->merge($this->fromChinaWorkflow($order))
            ->merge($this->fromWarehouse($order))
            ->merge($this->fromCustomerAgent($order))
            ->merge($this->fromShipmentTracking($order));

        $entries = $this->dedupe($entries);

        if ($visibility === TimelineVisibility::Customer) {
            $entries = $entries->filter(
                fn (array $e) => ($e['visibility'] ?? TimelineVisibility::Customer->value) === TimelineVisibility::Customer->value
            );
        }

        return $entries
            ->sortBy(fn (array $e) => $e['occurred_at'] ?? '')
            ->values()
            ->all();
    }

    /**
     * Persist composed projection into order_tracking_events.
     * Rebuild is side-effect free: no notifications, no business writes.
     *
     * @return list<OrderTrackingEvent>
     */
    public function rebuildProjection(Order $order): array
    {
        $composed = $this->compose($order, TimelineVisibility::Internal);

        OrderTrackingEvent::query()->where('order_id', $order->id)->delete();

        $created = [];
        foreach ($composed as $entry) {
            $created[] = OrderTrackingEvent::query()->create([
                'order_id' => $order->id,
                'code' => $entry['code'],
                'status' => $entry['code'],
                'visibility' => $entry['visibility'],
                'source_module' => $entry['source_module'],
                'actor_type' => $entry['actor_type'] ?? null,
                'actor_id' => $entry['actor_id'] ?? null,
                'correlation_key' => $entry['correlation_key'],
                'location' => $entry['location'] ?? null,
                'description' => $entry['label'],
                'metadata' => $entry['metadata'] ?? null,
                'occurred_at' => $entry['occurred_at'],
            ]);
        }

        return $created;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromOrderLifecycle(Order $order): Collection
    {
        return OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get()
            ->map(function (OrderStatusHistory $row) {
                $code = $this->mapOrderStatus((string) $row->new_status);
                $customerVisible = in_array($code, [
                    'order_created',
                    'payment_confirmed',
                    'processing',
                    'shipped',
                    'delivered',
                    'cancelled',
                    'refund_pending',
                    'refunded',
                ], true);

                return $this->entry(
                    code: $code,
                    label: $this->labelForCode($code),
                    occurredAt: optional($row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                    source: TimelineSourceModule::OrderLifecycle,
                    visibility: $customerVisible ? TimelineVisibility::Customer : TimelineVisibility::Internal,
                    correlationKey: $row->idempotency_key ?? 'lifecycle:'.$row->id,
                    actorType: $row->actor_type,
                    actorId: $row->changed_by_admin_id ?? $row->changed_by_user_id,
                    metadata: [
                        'previous_status' => $row->previous_status,
                        'new_status' => $row->new_status,
                        'source' => $row->source,
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromShipmentStatusHistory(Order $order): Collection
    {
        return $order->shipmentStatusHistories
            ->sortBy('created_at')
            ->values()
            ->map(function (ShipmentStatusHistory $row) {
                $code = 'journey_'.(string) $row->new_status;

                return $this->entry(
                    code: $code,
                    label: (string) $row->new_status,
                    occurredAt: optional($row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                    source: TimelineSourceModule::ChinaWorkflow,
                    visibility: TimelineVisibility::Customer,
                    correlationKey: $row->idempotency_key ?? 'shipment_status:'.$row->id,
                    actorType: $row->admin_id ? 'admin' : 'system',
                    actorId: $row->admin_id,
                    metadata: [
                        'previous_status' => $row->previous_status,
                        'new_status' => $row->new_status,
                        'source' => $row->source ?? null,
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromChinaWorkflow(Order $order): Collection
    {
        return ChinaWorkflowHistory::query()
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get()
            ->map(function (ChinaWorkflowHistory $row) {
                [$code, $visibility, $label] = $this->mapChinaAction((string) $row->action);

                return $this->entry(
                    code: $code,
                    label: $label,
                    occurredAt: optional($row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                    source: TimelineSourceModule::ChinaWorkflow,
                    visibility: $visibility,
                    correlationKey: $row->idempotency_key ?? 'china:'.$row->id,
                    actorType: $row->admin_id ? 'admin' : 'system',
                    actorId: $row->admin_id,
                    metadata: [
                        'action' => $row->action,
                        'from_stage' => $row->from_stage,
                        'to_stage' => $row->to_stage,
                        // Internal only — never expose raw reason/notes to customer visibility path.
                        'reason' => $visibility === TimelineVisibility::Internal ? $row->reason : null,
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromWarehouse(Order $order): Collection
    {
        $job = $order->fulfillment?->warehouseJob ?? $order->warehouseJob;
        if ($job === null) {
            return collect();
        }

        $entries = collect();
        $status = $job->status instanceof WarehouseJobStatus
            ? $job->status
            : WarehouseJobStatus::tryFrom((string) $job->status);

        $milestones = [
            ['at' => $job->picked_at ?? null, 'code' => 'packing_started', 'label' => 'Warehouse picking completed', 'customer' => false],
            ['at' => $job->packed_at ?? null, 'code' => 'packing_completed', 'label' => 'Packing completed', 'customer' => true],
            ['at' => $job->ready_at ?? null, 'code' => 'ready_to_ship', 'label' => 'Ready for dispatch', 'customer' => true],
        ];

        foreach ($milestones as $m) {
            if ($m['at'] === null) {
                continue;
            }
            $entries->push($this->entry(
                code: $m['code'],
                label: $m['label'],
                occurredAt: \Illuminate\Support\Carbon::parse($m['at'])->toIso8601String(),
                source: TimelineSourceModule::Warehouse,
                visibility: $m['customer'] ? TimelineVisibility::Customer : TimelineVisibility::Internal,
                correlationKey: 'warehouse:'.$job->id.':'.$m['code'],
                actorType: 'system',
                actorId: null,
                metadata: [
                    'warehouse_job_id' => $job->id,
                    'job_status' => $status?->value,
                ],
            ));
        }

        return $entries;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromCustomerAgent(Order $order): Collection
    {
        $type = $order->deliveryOption?->delivery_type instanceof DeliveryType
            ? $order->deliveryOption->delivery_type
            : DeliveryType::tryFrom((string) ($order->deliveryOption?->delivery_type ?? ''));

        if ($type !== DeliveryType::CustomerAgent) {
            return collect();
        }

        return CustomerAgentPickupHistory::query()
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get()
            ->map(function (CustomerAgentPickupHistory $row) {
                [$code, $visibility, $label] = $this->mapAgentAction((string) $row->action);

                return $this->entry(
                    code: $code,
                    label: $label,
                    occurredAt: optional($row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                    source: TimelineSourceModule::CustomerAgent,
                    visibility: $visibility,
                    correlationKey: $row->idempotency_key ?? 'agent:'.$row->id,
                    actorType: $row->admin_id ? 'admin' : 'system',
                    actorId: $row->admin_id,
                    metadata: [
                        'action' => $row->action,
                        'from_status' => $row->from_status,
                        'to_status' => $row->to_status,
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromShipmentTracking(Order $order): Collection
    {
        $shipment = $order->fulfillment?->shipment;
        if ($shipment === null) {
            return collect();
        }

        $events = $shipment->relationLoaded('trackingEvents')
            ? $shipment->trackingEvents
            : ShipmentTrackingEvent::query()->where('shipment_id', $shipment->id)->orderBy('event_at')->get();

        return collect($events)->map(function (ShipmentTrackingEvent $row) {
            $type = is_object($row->event_type) ? $row->event_type->value : (string) $row->event_type;
            $code = $type === 'delivered' ? 'delivered' : 'shipment_'.$type;

            return $this->entry(
                code: $code,
                label: is_object($row->event_type) ? $row->event_type->label() : $type,
                occurredAt: optional($row->event_at ?? $row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                source: TimelineSourceModule::Tracking,
                visibility: TimelineVisibility::Customer,
                correlationKey: $row->idempotency_key ?? 'track:'.$row->id,
                actorType: $row->created_by ? 'admin' : 'system',
                actorId: $row->created_by,
                location: $row->location,
                metadata: [
                    'shipment_id' => $row->shipment_id,
                    'event_type' => $type,
                ],
            );
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function dedupe(Collection $entries): Collection
    {
        $seen = [];
        $out = collect();

        foreach ($entries->sortBy('occurred_at') as $entry) {
            $key = $entry['correlation_key'] ?? ($entry['code'].'|'.$entry['occurred_at']);
            // Collapse duplicate logical customer codes (e.g. delivered from lifecycle + tracking).
            $logical = $entry['code'].'|'.$entry['visibility'];
            if (isset($seen[$key]) || (in_array($entry['code'], ['delivered', 'payment_confirmed', 'order_created'], true) && isset($seen[$logical]))) {
                continue;
            }
            $seen[$key] = true;
            $seen[$logical] = true;
            $out->push($entry);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    private function entry(
        string $code,
        string $label,
        string $occurredAt,
        TimelineSourceModule $source,
        TimelineVisibility $visibility,
        string $correlationKey,
        ?string $actorType,
        ?string $actorId,
        ?array $metadata = null,
        ?string $location = null,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'occurred_at' => $occurredAt,
            'source_module' => $source->value,
            'visibility' => $visibility->value,
            'correlation_key' => $correlationKey,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'location' => $location,
            'metadata' => $metadata,
        ];
    }

    private function mapOrderStatus(string $status): string
    {
        return match ($status) {
            OrderStatus::PendingPayment->value, 'pending_payment' => 'order_created',
            OrderStatus::Paid->value, 'paid' => 'payment_confirmed',
            OrderStatus::Processing->value, 'processing' => 'processing',
            OrderStatus::Shipped->value, 'shipped' => 'shipped',
            OrderStatus::Delivered->value, 'delivered' => 'delivered',
            OrderStatus::Cancelled->value, 'cancelled' => 'cancelled',
            OrderStatus::RefundPending->value, 'refund_pending' => 'refund_pending',
            OrderStatus::Refunded->value, 'refunded' => 'refunded',
            default => 'order_status_'.$status,
        };
    }

    /**
     * @return array{0: string, 1: TimelineVisibility, 2: string}
     */
    private function mapChinaAction(string $action): array
    {
        return match (true) {
            str_starts_with($action, 'qc_passed') || $action === 'qc_passed' => ['qc_passed', TimelineVisibility::Customer, 'Quality check passed'],
            str_starts_with($action, 'qc_') => ['qc_internal', TimelineVisibility::Internal, 'QC update'],
            $action === 'export_ready' => ['export_ready', TimelineVisibility::Customer, 'Export ready'],
            $action === 'consolidated' => ['consolidation_completed', TimelineVisibility::Customer, 'Consolidation completed'],
            $action === 'bootstrapped', $action === 'procurement_started' => ['procurement_started', TimelineVisibility::Customer, 'Procurement started'],
            $action === 'supplier_response' => ['procurement_update', TimelineVisibility::Internal, 'Supplier response'],
            $action === 'agent_handoff' => ['handed_to_customer_agent', TimelineVisibility::Customer, 'Handed to customer agent'],
            default => ['china_'.$action, TimelineVisibility::Internal, 'China workflow: '.$action],
        };
    }

    /**
     * @return array{0: string, 1: TimelineVisibility, 2: string}
     */
    private function mapAgentAction(string $action): array
    {
        return match ($action) {
            'authorization_issued' => ['pickup_authorized', TimelineVisibility::Customer, 'Pickup authorized'],
            'authorization_revoked' => ['pickup_authorization_revoked', TimelineVisibility::Customer, 'Pickup authorization revoked'],
            'pickup_scheduled' => ['pickup_scheduled', TimelineVisibility::Customer, 'Pickup scheduled'],
            'warehouse_release_ready_for_pickup' => ['pickup_ready', TimelineVisibility::Customer, 'Ready for agent pickup'],
            'warehouse_release_released' => ['warehouse_released', TimelineVisibility::Customer, 'Warehouse released to agent'],
            'agent_arrived' => ['agent_arrived', TimelineVisibility::Customer, 'Agent arrived'],
            'handover_completed' => ['handed_to_customer_agent', TimelineVisibility::Customer, 'Handed to customer agent'],
            'bootstrapped' => ['agent_pickup_bootstrapped', TimelineVisibility::Internal, 'Agent pickup bootstrapped'],
            default => ['agent_'.$action, TimelineVisibility::Internal, 'Agent: '.$action],
        };
    }

    private function labelForCode(string $code): string
    {
        return match ($code) {
            'order_created' => 'Order created',
            'payment_confirmed' => 'Payment confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refund_pending' => 'Refund pending',
            'refunded' => 'Refunded',
            default => str_replace('_', ' ', ucfirst($code)),
        };
    }
}
