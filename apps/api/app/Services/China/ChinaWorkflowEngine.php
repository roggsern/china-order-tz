<?php

namespace App\Services\China;

use App\Enums\ChinaExportReadiness;
use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStrategy;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\SupplierPoResponse;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\ChinaWorkflowHistory;
use App\Models\ChinaWorkflowRecord;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ShipmentStatusHistory;
use App\Models\SupplierProduct;
use App\Models\WarehouseJob;
use App\Services\Procurement\PurchaseOrderEngine;
use App\Shipments\OrderShipmentStatusResolver;
use App\Shipments\ShipmentStatusTransitionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * China Workflow orchestrator — authoritative China specialist path.
 *
 * NEVER writes orders.status (OrderLifecycleEngine remains sole authority).
 *
 * ============================================================
 * STATE OWNERSHIP (exactly one owner each)
 * ============================================================
 * QC state              → ChinaWorkflowRecord.qc_status (set only via recordQc)
 * Consolidation state   → ChinaWorkflowRecord.consolidation_* (set only via completeConsolidation)
 * Packing state         → WarehouseJob.status (WarehouseEngine)
 * Document/checklist    → ChinaWorkflowRecord.export_checklist (submitted to markExportReady)
 * Export Ready state    → ChinaWorkflowRecord.export_ready_at  ★ THIS ENGINE ONLY
 * Shipment eligibility  → ShipmentEligibilityService (consumes Export Ready; never computes it)
 *
 * Operational modules REPORT completion. ChinaWorkflowEngine alone EVALUATES and SETS Export Ready.
 */
class ChinaWorkflowEngine
{
    public function __construct(
        private readonly PurchaseOrderEngine $purchaseOrders,
        private readonly OrderShipmentStatusResolver $timelineResolver,
        private readonly ShipmentStatusTransitionValidator $timelineValidator,
    ) {}

    /**
     * Bootstrap China procurement after China fulfillment is created (idempotent).
     */
    public function bootstrapFromFulfillment(Fulfillment $fulfillment, ?Admin $admin = null): ChinaWorkflowRecord
    {
        $fulfillment->loadMissing(['order.items.product', 'order.items.variant', 'order.deliveryOption']);

        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) $fulfillment->strategy);

        if ($strategy !== FulfillmentStrategy::China) {
            throw ValidationException::withMessages([
                'fulfillment' => ['China workflow only applies to China fulfillment strategy.'],
            ]);
        }

        return DB::transaction(function () use ($fulfillment, $admin): ChinaWorkflowRecord {
            /** @var Order $order */
            $order = Order::query()->whereKey($fulfillment->order_id)->lockForUpdate()->firstOrFail();

            $existing = ChinaWorkflowRecord::query()->where('order_id', $order->id)->first();
            if ($existing !== null) {
                return $existing->load(['histories', 'fulfillment']);
            }

            $record = ChinaWorkflowRecord::query()->create([
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'stage' => ChinaWorkflowStage::AwaitingProcurement,
                'qc_status' => ChinaQcStatus::Pending,
                'metadata' => [
                    'bootstrapped_at' => now()->toIso8601String(),
                ],
            ]);

            $this->writeHistory($record, $admin, 'bootstrapped', null, ChinaWorkflowStage::AwaitingProcurement, 'China fulfillment bootstrap', [
                'fulfillment_id' => $fulfillment->id,
            ], 'china-bootstrap:'.$order->id);

            $this->ensureTimelineAtLeast($order, ShipmentStatus::PaymentConfirmed, $admin, 'china-pay:'.$order->id);
            $this->ensureTimelineAtLeast($order, ShipmentStatus::SupplierProcessing, $admin, 'china-supplier-proc:'.$order->id);

            $pos = $this->createPurchaseOrdersForOrder($order, $fulfillment, $admin);

            $record->forceFill([
                'stage' => ChinaWorkflowStage::ProcurementInProgress,
                'metadata' => array_merge($record->metadata ?? [], [
                    'purchase_order_ids' => array_map(fn (PurchaseOrder $po) => $po->id, $pos),
                    'supplier_count' => count($pos),
                ]),
            ])->save();

            $this->writeHistory(
                $record,
                $admin,
                'procurement_started',
                ChinaWorkflowStage::AwaitingProcurement,
                ChinaWorkflowStage::ProcurementInProgress,
                'Purchase orders generated for China order',
                ['purchase_order_count' => count($pos)],
                'china-procurement:'.$order->id,
            );

            return $record->fresh(['histories', 'fulfillment']) ?? $record;
        });
    }

    /**
     * @return list<PurchaseOrder>
     */
    public function createPurchaseOrdersForOrder(Order $order, Fulfillment $fulfillment, ?Admin $admin = null): array
    {
        $order->loadMissing(['items.product', 'items.variant']);
        $groups = $this->groupItemsBySupplier($order);

        if ($groups === []) {
            throw ValidationException::withMessages([
                'suppliers' => [
                    'No internal supplier mapping found for China order items. Map SupplierProduct or Product.supplier_id first.',
                ],
            ]);
        }

        $created = [];
        foreach ($groups as $supplierId => $lines) {
            $key = 'china-po:'.$order->id.':'.$supplierId;
            $existing = PurchaseOrder::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                $created[] = $existing;
                continue;
            }

            $po = $this->purchaseOrders->create([
                'supplier_id' => $supplierId,
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'idempotency_key' => $key,
                'currency' => $order->currency ?? 'TZS',
                'notes' => 'Auto-generated for China order '.$order->order_number,
                'items' => $lines,
            ], $admin);

            $created[] = $po;
        }

        return $created;
    }

    public function recordSupplierResponse(
        PurchaseOrder $po,
        SupplierPoResponse $response,
        ?string $notes,
        Admin $admin,
    ): PurchaseOrder {
        return DB::transaction(function () use ($po, $response, $notes, $admin): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();

            if ($locked->order_id === null) {
                throw ValidationException::withMessages([
                    'purchase_order' => ['Supplier response applies to China order-linked POs.'],
                ]);
            }

            $current = SupplierPoResponse::tryFrom((string) ($locked->supplier_response ?? 'pending'))
                ?? SupplierPoResponse::Pending;

            if ($current === $response && $locked->supplier_responded_at !== null) {
                return $locked->fresh() ?? $locked;
            }

            if ($current === SupplierPoResponse::Rejected && $response !== SupplierPoResponse::Rejected) {
                // Immutable rejection — reassignment creates a new PO; keep history.
                throw ValidationException::withMessages([
                    'supplier_response' => ['Rejected supplier responses are immutable. Create a replacement PO.'],
                ]);
            }

            $locked->forceFill([
                'supplier_response' => $response->value,
                'supplier_response_notes' => $notes,
                'supplier_responded_at' => now(),
            ])->save();

            if ($response === SupplierPoResponse::Accepted || $response === SupplierPoResponse::PartiallyAccepted) {
                $fresh = $locked->fresh() ?? $locked;
                if ($fresh->status === PurchaseOrderStatus::Draft) {
                    $fresh = $this->purchaseOrders->updateStatus($fresh, ['status' => PurchaseOrderStatus::Sent->value], $admin);
                }
                if ($fresh->status === PurchaseOrderStatus::Sent) {
                    $locked = $this->purchaseOrders->updateStatus($fresh, ['status' => PurchaseOrderStatus::Confirmed->value], $admin);
                } else {
                    $locked = $fresh;
                }
            }

            if ($response === SupplierPoResponse::Rejected) {
                $fresh = $locked->fresh() ?? $locked;
                if (in_array(PurchaseOrderStatus::Cancelled, $fresh->status->allowedTransitions(), true)) {
                    $locked = $this->purchaseOrders->updateStatus($fresh, [
                        'status' => PurchaseOrderStatus::Cancelled->value,
                        'notes' => $notes ?? 'Supplier rejected PO',
                    ], $admin);
                }
            }

            $record = ChinaWorkflowRecord::query()->where('order_id', $locked->order_id)->first();
            if ($record !== null) {
                $this->writeHistory(
                    $record,
                    $admin,
                    'supplier_response',
                    $record->stage,
                    $record->stage,
                    $notes ?? $response->label(),
                    [
                        'purchase_order_id' => $locked->id,
                        'supplier_response' => $response->value,
                    ],
                    'china-supplier-response:'.$locked->id.':'.$response->value,
                );
            }

            return $locked->fresh(['items', 'supplier']) ?? $locked;
        });
    }

    public function syncAfterReceiving(PurchaseOrder $po, ?Admin $admin = null): ?ChinaWorkflowRecord
    {
        if ($po->order_id === null) {
            return null;
        }

        return DB::transaction(function () use ($po, $admin): ChinaWorkflowRecord {
            $record = ChinaWorkflowRecord::query()
                ->where('order_id', $po->order_id)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw ValidationException::withMessages([
                    'workflow' => ['China workflow record missing for order-linked PO.'],
                ]);
            }

            $order = Order::query()->whereKey($po->order_id)->lockForUpdate()->firstOrFail();
            $pos = PurchaseOrder::query()
                ->where('order_id', $order->id)
                ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
                ->with('items')
                ->get();

            $anyReceived = $pos->contains(fn (PurchaseOrder $p) => $p->hasAnyReceived());
            $allFullyReceived = $pos->isNotEmpty() && $pos->every(fn (PurchaseOrder $p) => $p->isFullyReceived());

            $from = $record->stage;
            if ($allFullyReceived) {
                $to = ChinaWorkflowStage::QcPending;
                $record->forceFill([
                    'stage' => $to,
                    'qc_status' => ChinaQcStatus::Pending,
                ])->save();

                $this->ensureTimelineAtLeast($order, ShipmentStatus::PurchasedFromSupplier, $admin, 'china-purchased:'.$order->id);
                $this->ensureTimelineAtLeast($order, ShipmentStatus::ArrivedChinaWarehouse, $admin, 'china-arrived:'.$order->id);
                $this->ensureTimelineAtLeast($order, ShipmentStatus::QualityInspection, $admin, 'china-qc:'.$order->id);
            } elseif ($anyReceived) {
                $to = ChinaWorkflowStage::PartiallyReceived;
                $record->forceFill(['stage' => $to])->save();
                $this->ensureTimelineAtLeast($order, ShipmentStatus::PurchasedFromSupplier, $admin, 'china-purchased:'.$order->id);
                $this->ensureTimelineAtLeast($order, ShipmentStatus::ArrivedChinaWarehouse, $admin, 'china-arrived:'.$order->id);
            } else {
                return $record;
            }

            $this->writeHistory(
                $record,
                $admin,
                'receiving_synced',
                $from,
                $record->stage,
                'Receiving synced into China workflow',
                ['purchase_order_id' => $po->id, 'all_received' => $allFullyReceived],
                'china-receive-sync:'.$po->id.':'.($po->fresh()?->updated_at?->timestamp ?? time()),
            );

            return $record->fresh() ?? $record;
        });
    }

    public function recordQc(
        Order $order,
        ChinaQcStatus $status,
        ?string $notes,
        Admin $admin,
        ?string $idempotencyKey = null,
    ): ChinaWorkflowRecord {
        return DB::transaction(function () use ($order, $status, $notes, $admin, $idempotencyKey): ChinaWorkflowRecord {
            $record = $this->lockRecord($order);
            $key = $idempotencyKey ?? 'china-qc:'.$order->id.':'.$status->value;

            if ($this->historyExists($key)) {
                return $record->fresh() ?? $record;
            }

            if (! in_array($record->stage, [
                ChinaWorkflowStage::QcPending,
                ChinaWorkflowStage::QcFailed,
                ChinaWorkflowStage::Received,
                ChinaWorkflowStage::PartiallyReceived,
            ], true) && $status !== ChinaQcStatus::Reinspection) {
                // Allow QC from qc_pending primarily; received maps to pending first.
                if ($record->stage === ChinaWorkflowStage::Received
                    || $record->stage === ChinaWorkflowStage::PartiallyReceived
                ) {
                    $record->forceFill(['stage' => ChinaWorkflowStage::QcPending])->save();
                } elseif ($record->stage !== ChinaWorkflowStage::QcPending
                    && $record->stage !== ChinaWorkflowStage::QcFailed
                ) {
                    throw ValidationException::withMessages([
                        'qc' => ['QC can only run after China warehouse receiving.'],
                    ]);
                }
            }

            $from = $record->stage;
            $to = match ($status) {
                ChinaQcStatus::Passed => ChinaWorkflowStage::QcPassed,
                ChinaQcStatus::Failed => ChinaWorkflowStage::QcFailed,
                ChinaQcStatus::Hold, ChinaQcStatus::Reinspection => ChinaWorkflowStage::QcPending,
                ChinaQcStatus::Pending => ChinaWorkflowStage::QcPending,
            };

            $record->forceFill([
                'stage' => $to,
                'qc_status' => $status,
                'qc_notes' => $notes,
                'qc_admin_id' => $admin->id,
                'qc_at' => now(),
            ])->save();

            $this->writeHistory($record, $admin, 'qc_'.$status->value, $from, $to, $notes, [
                'qc_status' => $status->value,
            ], $key);

            if ($status === ChinaQcStatus::Passed) {
                $this->ensureTimelineAtLeast($order, ShipmentStatus::QualityInspection, $admin, 'china-qc-timeline:'.$order->id);
            }

            return $record->fresh() ?? $record;
        });
    }

    public function completeConsolidation(Order $order, Admin $admin, ?string $batch = null): ChinaWorkflowRecord
    {
        return DB::transaction(function () use ($order, $admin, $batch): ChinaWorkflowRecord {
            $record = $this->lockRecord($order);
            $key = 'china-consolidation:'.$order->id;

            if ($record->stage === ChinaWorkflowStage::Consolidated && $record->consolidation_completed_at !== null) {
                return $record;
            }

            if ($record->qc_status !== ChinaQcStatus::Passed) {
                throw ValidationException::withMessages([
                    'consolidation' => ['QC must pass before consolidation. Failed QC cannot continue to export.'],
                ]);
            }

            $from = $record->stage;
            $supplierCount = PurchaseOrder::query()
                ->where('order_id', $order->id)
                ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
                ->count();

            $record->forceFill([
                'stage' => ChinaWorkflowStage::Consolidated,
                'consolidation_batch' => $batch ?: 'BATCH-'.Str::upper(Str::random(8)),
                'consolidation_completed_at' => now(),
                'metadata' => array_merge($record->metadata ?? [], [
                    'consolidated_supplier_count' => $supplierCount,
                ]),
            ])->save();

            $this->writeHistory(
                $record,
                $admin,
                'consolidated',
                $from,
                ChinaWorkflowStage::Consolidated,
                'Multi-supplier goods consolidated for export',
                ['batch' => $record->consolidation_batch],
                $key,
            );

            return $record->fresh() ?? $record;
        });
    }

    /**
     * Authoritative Export Readiness evaluation + transition.
     * Sole writer of export_ready_at. No other module may set Export Ready.
     *
     * @param  array{
     *     commercial_invoice?: bool,
     *     packing_list?: bool,
     *     customs_docs?: bool,
     *     weight_confirmed?: bool,
     *     dimensions_confirmed?: bool,
     *     carton_count?: int|null,
     *     notes?: string|null
     * }  $checklist
     */
    public function markExportReady(Order $order, Admin $admin, array $checklist = []): ChinaWorkflowRecord
    {
        return DB::transaction(function () use ($order, $admin, $checklist): ChinaWorkflowRecord {
            $record = $this->lockRecord($order);
            $key = 'china-export-ready:'.$order->id;

            if ($record->isAuthoritativelyExportReady()) {
                return $record;
            }

            $blockers = $this->evaluateExportReadinessBlockers($record, $order, $checklist);
            if ($blockers !== []) {
                throw ValidationException::withMessages([
                    'export' => $blockers,
                ]);
            }

            $supplierCount = $this->activeSupplierPoCount($order);
            if ($supplierCount === 1 && $record->consolidation_completed_at === null) {
                // Single-supplier: consolidation not required — stamp completion for audit clarity.
                $record->forceFill([
                    'consolidation_completed_at' => now(),
                    'consolidation_batch' => $record->consolidation_batch ?? 'SINGLE-'.$order->order_number,
                ])->save();
            }

            $from = $record->stage;
            $fromReadiness = ChinaExportReadiness::NotReady;
            $order->loadMissing('deliveryOption');
            $deliveryType = $order->deliveryOption?->delivery_type;
            $type = $deliveryType instanceof DeliveryType
                ? $deliveryType
                : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

            $next = $type === DeliveryType::CustomerAgent
                ? ChinaWorkflowStage::ExportReady
                : ChinaWorkflowStage::CompanyShippingReady;

            $record->forceFill([
                'stage' => $next,
                'export_checklist' => $checklist,
                'export_ready_at' => now(),
                'export_approved_by' => $admin->id,
            ])->save();

            $this->ensureTimelineAtLeast($order, ShipmentStatus::PackedForExport, $admin, 'china-packed-export:'.$order->id);

            $this->writeHistory(
                $record,
                $admin,
                'export_ready',
                $from,
                $next,
                $checklist['notes'] ?? 'Export readiness approved by ChinaWorkflowEngine',
                [
                    'checklist' => $checklist,
                    'delivery_type' => $type?->value,
                    'export_readiness_from' => $fromReadiness->value,
                    'export_readiness_to' => ChinaExportReadiness::ExportReady->value,
                    'authority' => 'ChinaWorkflowEngine',
                ],
                $key,
            );

            return $record->fresh() ?? $record;
        });
    }

    public function recordAgentHandoff(
        Order $order,
        Admin $admin,
        string $agentName,
        ?string $agentContact = null,
        ?string $evidence = null,
    ): ChinaWorkflowRecord {
        return DB::transaction(function () use ($order, $admin, $agentName, $agentContact, $evidence): ChinaWorkflowRecord {
            $record = $this->lockRecord($order);
            $key = 'china-agent-handoff:'.$order->id;

            if ($record->stage === ChinaWorkflowStage::AgentHandedOff && $record->agent_handed_off_at !== null) {
                return $record;
            }

            if (! $record->isAuthoritativelyExportReady()) {
                throw ValidationException::withMessages([
                    'agent' => ['Agent handoff requires export readiness first.'],
                ]);
            }

            $order->loadMissing('deliveryOption');
            $type = $order->deliveryOption?->delivery_type;
            $deliveryType = $type instanceof DeliveryType
                ? $type
                : DeliveryType::tryFrom((string) ($type ?? ''));

            if ($deliveryType !== DeliveryType::CustomerAgent) {
                throw ValidationException::withMessages([
                    'agent' => ['Agent handoff only applies to Customer Agent delivery.'],
                ]);
            }

            $from = $record->stage;
            $record->forceFill([
                'stage' => ChinaWorkflowStage::AgentHandedOff,
                'agent_name' => $agentName,
                'agent_contact' => $agentContact,
                'agent_evidence' => $evidence,
                'agent_handed_off_at' => now(),
                'agent_admin_id' => $admin->id,
            ])->save();

            $this->writeHistory(
                $record,
                $admin,
                'agent_handoff',
                $from,
                ChinaWorkflowStage::AgentHandedOff,
                'Customer shipping agent handoff',
                [
                    'agent_name' => $agentName,
                    'agent_contact' => $agentContact,
                    'evidence' => $evidence,
                ],
                $key,
            );

            return $record->fresh() ?? $record;
        });
    }

    /**
     * Read-only Export Ready query for consumers (ShipmentEligibilityService, etc.).
     * NEVER recalculates QC / consolidation / packing / checklist.
     */
    public function isExportReadyForShipment(Fulfillment $fulfillment): bool
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) $fulfillment->strategy);

        if ($strategy !== FulfillmentStrategy::China) {
            return true;
        }

        $record = ChinaWorkflowRecord::query()->where('order_id', $fulfillment->order_id)->first();

        // Legacy China fulfillments without a bootstrapped workflow: no Export Ready gate.
        if ($record === null) {
            return true;
        }

        return $record->isAuthoritativelyExportReady();
    }

    /**
     * @deprecated Prefer isExportReadyForShipment — kept for call-site clarity during transition.
     */
    public function assertExportReadyForShipment(Fulfillment $fulfillment): void
    {
        if (! $this->isExportReadyForShipment($fulfillment)) {
            throw ValidationException::withMessages([
                'fulfillment' => ['China export readiness is required before company shipment.'],
            ]);
        }
    }

    /**
     * Evaluate all Export Ready requirements. Used ONLY by markExportReady.
     * Returns a list of human-readable blockers (empty = may become EXPORT_READY).
     *
     * @param  array<string, mixed>  $checklist
     * @return list<string>
     */
    public function evaluateExportReadinessBlockers(
        ChinaWorkflowRecord $record,
        Order $order,
        array $checklist = [],
    ): array {
        $blockers = [];

        if ($record->qc_status === ChinaQcStatus::Failed) {
            $blockers[] = 'Export blocked: QC failed.';
        } elseif ($record->qc_status === ChinaQcStatus::Hold) {
            $blockers[] = 'Export blocked: unresolved QC/export hold.';
        } elseif ($record->qc_status !== ChinaQcStatus::Passed) {
            $blockers[] = 'Export blocked: QC has not passed.';
        }

        if (in_array($record->stage, [
            ChinaWorkflowStage::QcFailed,
            ChinaWorkflowStage::QcPending,
            ChinaWorkflowStage::PartiallyReceived,
            ChinaWorkflowStage::Received,
            ChinaWorkflowStage::AwaitingProcurement,
            ChinaWorkflowStage::ProcurementInProgress,
            ChinaWorkflowStage::Consolidating,
        ], true)) {
            $blockers[] = 'Export blocked: workflow stage ['.$record->stage->value.'] is not eligible.';
        }

        $supplierCount = $this->activeSupplierPoCount($order);
        if ($supplierCount > 1 && $record->consolidation_completed_at === null) {
            $blockers[] = 'Multi-supplier orders require consolidation before export.';
        }

        if (! $this->isPackingCompleteForExport($order)) {
            $blockers[] = 'Export blocked: warehouse packing is incomplete (requires packed or ready_to_ship).';
        }

        $required = ['commercial_invoice', 'packing_list', 'customs_docs', 'weight_confirmed', 'dimensions_confirmed'];
        foreach ($required as $field) {
            if (! ($checklist[$field] ?? false)) {
                $blockers[] = "Export checklist item [{$field}] must be confirmed.";
            }
        }

        return array_values(array_unique($blockers));
    }

    public function exportReadinessForOrder(Order $order): ChinaExportReadiness
    {
        $record = ChinaWorkflowRecord::query()->where('order_id', $order->id)->first();
        if ($record === null) {
            return ChinaExportReadiness::NotReady;
        }

        return $record->exportReadiness();
    }

    public function showForOrder(Order $order): ?ChinaWorkflowRecord
    {
        return ChinaWorkflowRecord::query()
            ->with(['histories', 'fulfillment'])
            ->where('order_id', $order->id)
            ->first();
    }

    private function activeSupplierPoCount(Order $order): int
    {
        return PurchaseOrder::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
            ->count();
    }

    /**
     * Consumes WarehouseEngine packing state — does not own or mutate it.
     */
    private function isPackingCompleteForExport(Order $order): bool
    {
        $order->loadMissing('fulfillment.warehouseJob');
        $job = $order->fulfillment?->warehouseJob
            ?? WarehouseJob::query()->where('order_id', $order->id)->first();

        if ($job === null) {
            return false;
        }

        $status = $job->status instanceof WarehouseJobStatus
            ? $job->status
            : WarehouseJobStatus::tryFrom((string) $job->status);

        return in_array($status, [
            WarehouseJobStatus::Packed,
            WarehouseJobStatus::ReadyToShip,
        ], true);
    }

    private function lockRecord(Order $order): ChinaWorkflowRecord
    {
        $record = ChinaWorkflowRecord::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'workflow' => ['China workflow has not been bootstrapped for this order.'],
            ]);
        }

        return $record;
    }

    /**
     * @return array<string, list<array{product_variant_id: string, quantity_ordered: int, unit_cost: string}>>
     */
    private function groupItemsBySupplier(Order $order): array
    {
        $groups = [];

        foreach ($order->items as $item) {
            $variantId = $item->product_variant_id;
            if ($variantId === null) {
                continue;
            }

            $mapping = SupplierProduct::query()
                ->where('product_variant_id', $variantId)
                ->where('is_active', true)
                ->whereHas('supplier', fn ($q) => $q->where('is_active', true))
                ->orderByDesc('updated_at')
                ->first();

            $supplierId = $mapping?->supplier_id;
            if ($supplierId === null) {
                /** @var Product|null $product */
                $product = $item->product;
                $supplierId = $product?->supplier_id;
            }

            if ($supplierId === null) {
                continue;
            }

            $unitCost = $mapping?->purchase_cost
                ?? $item->unit_price_snapshot
                ?? $item->unit_price
                ?? '0.00';

            $groups[$supplierId][] = [
                'product_variant_id' => (string) $variantId,
                'quantity_ordered' => (int) $item->quantity,
                'unit_cost' => (string) $unitCost,
            ];
        }

        return $groups;
    }

    private function ensureTimelineAtLeast(
        Order $order,
        ShipmentStatus $target,
        ?Admin $admin,
        string $idempotencyKey,
    ): void {
        if (ShipmentStatusHistory::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $timeline = ShipmentStatus::timeline();
        $current = $this->timelineResolver->resolve($order);
        $currentIndex = array_search($current, $timeline, true);
        $targetIndex = array_search($target, $timeline, true);

        if ($currentIndex === false || $targetIndex === false || $targetIndex <= $currentIndex) {
            return;
        }

        for ($i = $currentIndex + 1; $i <= $targetIndex; $i++) {
            $step = $timeline[$i];
            $stepKey = $idempotencyKey.':'.$step->value;
            if (ShipmentStatusHistory::query()->where('idempotency_key', $stepKey)->exists()) {
                $order->refresh();
                continue;
            }

            $prev = $this->timelineResolver->resolve($order->fresh() ?? $order);
            try {
                $this->timelineValidator->validate($prev, $step);
            } catch (ValidationException $e) {
                Log::warning('china.timeline_step_skipped', [
                    'order_id' => $order->id,
                    'from' => $prev->value,
                    'to' => $step->value,
                    'message' => $e->getMessage(),
                ]);
                break;
            }

            ShipmentStatusHistory::query()->create([
                'order_id' => $order->id,
                'admin_id' => $admin?->id,
                'previous_status' => $prev->value,
                'new_status' => $step->value,
                'source' => 'china_workflow',
                'idempotency_key' => $stepKey,
            ]);

            $order->forceFill([
                'shipment_status' => $step,
                'shipment_status_updated_at' => now(),
            ])->save();
        }
    }

    private function writeHistory(
        ChinaWorkflowRecord $record,
        ?Admin $admin,
        string $action,
        ChinaWorkflowStage|string|null $from,
        ChinaWorkflowStage|string|null $to,
        ?string $reason,
        array $metadata,
        ?string $idempotencyKey,
    ): void {
        if ($idempotencyKey !== null && $this->historyExists($idempotencyKey)) {
            return;
        }

        ChinaWorkflowHistory::query()->create([
            'china_workflow_record_id' => $record->id,
            'order_id' => $record->order_id,
            'admin_id' => $admin?->id,
            'action' => $action,
            'from_stage' => $from instanceof ChinaWorkflowStage ? $from->value : $from,
            'to_stage' => $to instanceof ChinaWorkflowStage ? $to->value : $to,
            'reason' => $reason,
            'metadata' => $metadata !== [] ? $metadata : null,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function historyExists(string $key): bool
    {
        return ChinaWorkflowHistory::query()->where('idempotency_key', $key)->exists();
    }
}
