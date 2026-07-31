<?php

namespace App\Services\Fulfillment;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\AgentPickupStatus;
use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\LastMileReceivingMethod;
use App\Enums\PickupAuthorizationStatus;
use App\Enums\SupplierPoResponse;
use App\Enums\WarehouseJobStatus;
use App\Events\Audit\FulfillmentBulkActionCompleted;
use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Models\CustomerAgentPickup;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Services\Audit\ActivityLogger;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use App\Services\Procurement\ReceivingEngine;
use App\Services\Shipments\ShipmentEligibilityService;
use App\Services\Shipments\ShipmentEngine;
use App\Services\Warehouse\WarehouseEngine;
use App\Support\Admin\AdminPermissions;
use App\Support\Fulfillment\BulkActionResult;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FulfillmentBulkActionService
{
    private const ACTION_MARK_LOCAL_ORDER_READY = 'MARK_LOCAL_ORDER_READY';

    private const ACTION_MARK_LOCAL_ORDER_COMPLETED = 'MARK_LOCAL_ORDER_COMPLETED';

    private const ACTION_CREATE_SUPPLIER_PURCHASE = 'CREATE_SUPPLIER_PURCHASE';

    private const ACTION_RECEIVE_GOODS = 'RECEIVE_GOODS';

    private const ACTION_MARK_QC_PASSED = 'MARK_QC_PASSED';

    private const ACTION_MARK_CHINA_PACKING_COMPLETE = 'MARK_CHINA_PACKING_COMPLETE';

    private const ACTION_MARK_EXPORT_READY = 'MARK_EXPORT_READY';

    private const ACTION_MARK_AGENT_DELIVERED = 'MARK_AGENT_DELIVERED';

    private const ACTION_MARK_CUSTOMER_COLLECTED = 'MARK_CUSTOMER_COLLECTED';

    private const ACTION_MARK_CUSTOMER_DELIVERED = 'MARK_CUSTOMER_DELIVERED';

    private const ACTION_CREATE_SHIPMENT = 'CREATE_SHIPMENT';

    /** @var list<string> */
    private const EXPORT_READY_WORKFLOW_STAGES = [
        'qc_passed',
        'consolidated',
        'consolidating',
        'received',
    ];

    public function __construct(
        private readonly WarehouseEngine $warehouseEngine,
        private readonly LocalFulfillmentCompletionService $localCompletionService,
        private readonly ChinaWorkflowEngine $chinaWorkflowEngine,
        private readonly CustomerAgentWorkflowEngine $customerAgentWorkflowEngine,
        private readonly ReceivingEngine $receivingEngine,
        private readonly ShipmentEligibilityService $shipmentEligibilityService,
        private readonly ShipmentEngine $shipmentEngine,
        private readonly CompanyShippingHandoverService $companyShippingHandoverService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  list<string>  $fulfillmentIds
     * @return array{
     *     batch_id: string,
     *     action_key: string,
     *     total: int,
     *     succeeded: int,
     *     failed: int,
     *     skipped: int,
     *     results: list<array{fulfillment_id: string, success: bool, error?: string}>
     * }
     */
    public function execute(Admin $admin, string $actionKey, array $fulfillmentIds): array
    {
        $actionKey = strtoupper(trim($actionKey));
        $fulfillmentIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => is_string($id) ? trim($id) : '',
            $fulfillmentIds,
        ))));

        if ($fulfillmentIds === []) {
            throw ValidationException::withMessages([
                'fulfillment_ids' => ['At least one fulfilment id is required.'],
            ]);
        }

        if (! in_array($actionKey, [
            self::ACTION_MARK_LOCAL_ORDER_READY,
            self::ACTION_MARK_LOCAL_ORDER_COMPLETED,
            self::ACTION_CREATE_SUPPLIER_PURCHASE,
            self::ACTION_RECEIVE_GOODS,
            self::ACTION_MARK_QC_PASSED,
            self::ACTION_MARK_CHINA_PACKING_COMPLETE,
            self::ACTION_MARK_EXPORT_READY,
            self::ACTION_MARK_AGENT_DELIVERED,
            self::ACTION_MARK_CUSTOMER_COLLECTED,
            self::ACTION_MARK_CUSTOMER_DELIVERED,
            self::ACTION_CREATE_SHIPMENT,
        ], true)) {
            throw ValidationException::withMessages([
                'action_key' => ['Unsupported bulk action.'],
            ]);
        }

        $this->assertBulkPermissions($admin, $actionKey);

        $batchId = (string) Str::uuid();
        $results = [];
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($fulfillmentIds as $fulfillmentId) {
            $result = match ($actionKey) {
                self::ACTION_MARK_LOCAL_ORDER_READY => $this->processMarkLocalOrderReady($admin, $fulfillmentId),
                self::ACTION_MARK_LOCAL_ORDER_COMPLETED => $this->processMarkLocalOrderCompleted($admin, $fulfillmentId),
                self::ACTION_CREATE_SUPPLIER_PURCHASE => $this->processCreateSupplierPurchase($admin, $fulfillmentId),
                self::ACTION_RECEIVE_GOODS => $this->processReceiveGoods($admin, $fulfillmentId),
                self::ACTION_MARK_QC_PASSED => $this->processMarkQcPassed($admin, $fulfillmentId),
                self::ACTION_MARK_CHINA_PACKING_COMPLETE => $this->processMarkChinaPackingComplete($admin, $fulfillmentId),
                self::ACTION_MARK_EXPORT_READY => $this->processMarkExportReady($admin, $fulfillmentId),
                self::ACTION_MARK_AGENT_DELIVERED => $this->processMarkAgentDelivered($admin, $fulfillmentId),
                self::ACTION_MARK_CUSTOMER_COLLECTED => $this->processMarkCustomerCollected($admin, $fulfillmentId),
                self::ACTION_MARK_CUSTOMER_DELIVERED => $this->processMarkCustomerDelivered($admin, $fulfillmentId),
                self::ACTION_CREATE_SHIPMENT => $this->processCreateShipment($admin, $fulfillmentId),
            };
            $result = BulkActionResult::normalize($result);
            $results[] = $result;

            if ($result['status'] === 'succeeded') {
                $succeeded++;
            } elseif ($result['status'] === 'skipped') {
                $skipped++;
            } else {
                $failed++;
            }
        }

        $this->recordBulkAudit($admin, $batchId, $actionKey, count($fulfillmentIds), $succeeded, $failed, $skipped);

        return [
            'batch_id' => $batchId,
            'action_key' => $actionKey,
            'total' => count($fulfillmentIds),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
            'results' => array_map(
                static fn (array $row) => array_filter([
                    'fulfillment_id' => $row['fulfillment_id'],
                    'status' => $row['status'],
                    'success' => $row['success'],
                    'reason_code' => $row['reason_code'] ?? null,
                    'reason' => $row['reason'] ?? null,
                ], static fn ($value) => $value !== null),
                $results,
            ),
        ];
    }

    private function assertBulkPermissions(Admin $admin, string $actionKey): void
    {
        if ($actionKey === self::ACTION_CREATE_SUPPLIER_PURCHASE) {
            if (! $admin->hasAdminPermission(AdminPermissions::PROCUREMENT_CREATE)) {
                throw ValidationException::withMessages([
                    'permission' => ['You do not have permission to create supplier purchases.'],
                ]);
            }

            return;
        }

        if ($actionKey === self::ACTION_RECEIVE_GOODS) {
            if (! $admin->hasAdminPermission(AdminPermissions::PURCHASE_ORDERS_RECEIVE)) {
                throw ValidationException::withMessages([
                    'permission' => ['You do not have permission to receive purchase orders.'],
                ]);
            }

            return;
        }

        if ($actionKey === self::ACTION_MARK_QC_PASSED) {
            if (! $admin->hasAdminPermission(AdminPermissions::PROCUREMENT_UPDATE)) {
                throw ValidationException::withMessages([
                    'permission' => ['You do not have permission to record China QC decisions.'],
                ]);
            }

            return;
        }

        if ($actionKey === self::ACTION_MARK_CHINA_PACKING_COMPLETE) {
            if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_UPDATE)) {
                throw ValidationException::withMessages([
                    'permission' => ['You do not have permission to update warehouse jobs.'],
                ]);
            }

            if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_COMPLETE)) {
                throw ValidationException::withMessages([
                    'permission' => ['You do not have permission to complete warehouse preparation.'],
                ]);
            }

            return;
        }

        if ($actionKey === self::ACTION_MARK_EXPORT_READY) {
            if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_COMPLETE)) {
                throw ValidationException::withMessages([
                    'permission' => ['You do not have permission to approve export readiness.'],
                ]);
            }

            return;
        }

        if (in_array($actionKey, [
            self::ACTION_MARK_AGENT_DELIVERED,
            self::ACTION_CREATE_SHIPMENT,
        ], true)) {
            if (! $admin->hasAdminPermission(AdminPermissions::ORDERS_SHIP)) {
                throw ValidationException::withMessages([
                    'permission' => [$actionKey === self::ACTION_CREATE_SHIPMENT
                        ? 'You do not have permission to create shipments.'
                        : 'You do not have permission to deliver orders to customer agents.'],
                ]);
            }

            return;
        }

        if (in_array($actionKey, [
            self::ACTION_MARK_LOCAL_ORDER_COMPLETED,
            self::ACTION_MARK_CUSTOMER_COLLECTED,
            self::ACTION_MARK_CUSTOMER_DELIVERED,
        ], true)) {
            if (! $admin->hasAdminPermission(AdminPermissions::ORDERS_FULFILL)) {
                throw ValidationException::withMessages([
                    'permission' => ['You do not have permission to complete fulfilments.'],
                ]);
            }

            return;
        }

        if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_UPDATE)) {
            throw ValidationException::withMessages([
                'permission' => ['You do not have permission to update warehouse jobs.'],
            ]);
        }

        if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_COMPLETE)) {
            throw ValidationException::withMessages([
                'permission' => ['You do not have permission to complete warehouse preparation.'],
            ]);
        }
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processMarkLocalOrderReady(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'warehouseJob'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveMarkLocalOrderReadyIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        $job = $fulfillment->warehouseJob;
        if ($job === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Warehouse job not found.',
            ];
        }

        try {
            $current = $job->status instanceof WarehouseJobStatus
                ? $job->status
                : WarehouseJobStatus::tryFrom((string) ($job->status ?? ''));

            if ($current === null) {
                return [
                    'fulfillment_id' => $fulfillmentId,
                    'success' => false,
                    'error' => 'Warehouse job status is invalid.',
                ];
            }

            while ($current !== WarehouseJobStatus::ReadyToShip) {
                $next = $current->nextForward();
                if ($next === null) {
                    return [
                        'fulfillment_id' => $fulfillmentId,
                        'success' => false,
                        'error' => 'Order is already marked ready.',
                    ];
                }

                $this->assertWarehouseStatusPermission($admin, $next);

                $job = $this->warehouseEngine->updateStatus($job, [
                    'status' => $next->value,
                ]);
                $job->refresh();
                $current = $job->status instanceof WarehouseJobStatus
                    ? $job->status
                    : WarehouseJobStatus::tryFrom((string) ($job->status ?? ''));
            }

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to mark order ready.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to mark order ready.',
            ];
        }
    }

    private function resolveMarkLocalOrderReadyIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::Local) {
            return 'Only Buy From TZ fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return 'Fulfilment is already completed.';
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        if ($deliveryType instanceof DeliveryType) {
            $delivery = $deliveryType;
        } else {
            $delivery = DeliveryType::tryFrom((string) ($deliveryType ?? ''));
        }

        if ($delivery === DeliveryType::CustomerAgent) {
            return 'Customer agent delivery orders cannot use this bulk action.';
        }

        if ($delivery === DeliveryType::CompanyShipping) {
            return 'Company shipping orders cannot use this bulk action.';
        }

        if (! in_array($delivery, [DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery], true)) {
            return 'Delivery type is not eligible for Buy From TZ bulk ready action.';
        }

        $warehouseStatus = $fulfillment->warehouseJob?->status;
        $warehouse = $warehouseStatus instanceof WarehouseJobStatus
            ? $warehouseStatus
            : WarehouseJobStatus::tryFrom((string) ($warehouseStatus ?? ''));

        if ($warehouse === WarehouseJobStatus::ReadyToShip) {
            return 'Order is already marked ready.';
        }

        if ($warehouse === WarehouseJobStatus::Cancelled) {
            return 'Warehouse job is cancelled.';
        }

        return null;
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processMarkLocalOrderCompleted(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'warehouseJob'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveMarkLocalOrderCompletedIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        try {
            $this->localCompletionService->complete($fulfillment, $admin);

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to mark order completed.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to mark order completed.',
            ];
        }
    }

    private function resolveMarkLocalOrderCompletedIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::Local) {
            return 'Only Buy From TZ fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status === null || $status->isTerminal()) {
            return 'Fulfilment is already completed or cancelled.';
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        if ($deliveryType instanceof DeliveryType) {
            $delivery = $deliveryType;
        } else {
            $delivery = DeliveryType::tryFrom((string) ($deliveryType ?? ''));
        }

        if ($delivery === DeliveryType::CustomerAgent) {
            return 'Customer agent delivery orders cannot use this bulk action.';
        }

        if ($delivery === DeliveryType::CompanyShipping) {
            return 'Company shipping orders cannot use this bulk action.';
        }

        if (! in_array($delivery, [DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery], true)) {
            return 'Delivery type is not eligible for Buy From TZ bulk completion.';
        }

        if ($status !== FulfillmentStatus::ReadyForShipping) {
            return 'Fulfilment must be order-ready before it can be marked completed.';
        }

        $warehouseStatus = $fulfillment->warehouseJob?->status;
        $warehouse = $warehouseStatus instanceof WarehouseJobStatus
            ? $warehouseStatus
            : WarehouseJobStatus::tryFrom((string) ($warehouseStatus ?? ''));

        if ($warehouse !== WarehouseJobStatus::ReadyToShip) {
            return 'Warehouse preparation must be complete before marking the order completed.';
        }

        return null;
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processCreateSupplierPurchase(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'chinaWorkflowRecord'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveCreateSupplierPurchaseIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        try {
            $this->chinaWorkflowEngine->bootstrapFromFulfillment($fulfillment, $admin);

            if (! $this->hasActiveSupplierPurchase($fulfillment->fresh())) {
                return [
                    'fulfillment_id' => $fulfillmentId,
                    'success' => false,
                    'error' => 'Unable to create supplier purchase orders.',
                ];
            }

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to create supplier purchase orders.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to create supplier purchase orders.',
            ];
        }
    }

    private function resolveCreateSupplierPurchaseIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return 'Only China Import fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return 'Fulfilment is already completed or cancelled.';
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        $delivery = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        if ($delivery === DeliveryType::CustomerAgent) {
            return 'Customer agent delivery orders cannot use this bulk action.';
        }

        if ($this->hasActiveSupplierPurchase($fulfillment)) {
            return 'Supplier purchase already exists.';
        }

        $stage = $fulfillment->chinaWorkflowRecord?->stage;
        $workflowStage = $stage instanceof \App\Enums\ChinaWorkflowStage
            ? $stage
            : \App\Enums\ChinaWorkflowStage::tryFrom((string) ($stage ?? ''));

        if ($workflowStage !== null && $workflowStage !== \App\Enums\ChinaWorkflowStage::AwaitingProcurement) {
            return 'Fulfilment is not eligible for supplier purchase creation.';
        }

        return null;
    }

    private function hasActiveSupplierPurchase(Fulfillment $fulfillment): bool
    {
        return PurchaseOrder::query()
            ->where('fulfillment_id', $fulfillment->id)
            ->where('status', '!=', PurchaseOrderStatus::Cancelled)
            ->exists();
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processReceiveGoods(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'chinaWorkflowRecord', 'purchaseOrders.items'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveReceiveGoodsIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        $purchaseOrder = $this->resolveReceivablePurchaseOrder($fulfillment);
        if ($purchaseOrder === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'No receivable purchase order found.',
            ];
        }

        $items = $this->buildFullReceiveItems($purchaseOrder);
        if ($items === []) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Goods already received.',
            ];
        }

        try {
            $this->receivingEngine->receive($purchaseOrder, ['items' => $items], $admin);

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to receive goods.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to receive goods.',
            ];
        }
    }

    private function resolveReceiveGoodsIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return 'Only China Import fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return 'Fulfilment is already completed or cancelled.';
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        $delivery = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        if ($delivery === DeliveryType::CustomerAgent) {
            return 'Customer agent delivery orders cannot use this bulk action.';
        }

        if (! $this->hasActiveSupplierPurchase($fulfillment)) {
            return 'No active supplier purchase.';
        }

        $stage = $fulfillment->chinaWorkflowRecord?->stage;
        $workflowStage = $stage instanceof ChinaWorkflowStage
            ? $stage
            : ChinaWorkflowStage::tryFrom((string) ($stage ?? ''));

        if ($workflowStage === null) {
            return 'Fulfilment is not eligible for goods receipt.';
        }

        if (in_array($workflowStage, [
            ChinaWorkflowStage::Received,
            ChinaWorkflowStage::QcPending,
            ChinaWorkflowStage::QcFailed,
            ChinaWorkflowStage::QcPassed,
            ChinaWorkflowStage::Consolidating,
            ChinaWorkflowStage::Consolidated,
            ChinaWorkflowStage::ExportReady,
            ChinaWorkflowStage::AgentHandedOff,
            ChinaWorkflowStage::CompanyShippingReady,
        ], true)) {
            return 'Goods already received.';
        }

        if (! in_array($workflowStage, [
            ChinaWorkflowStage::ProcurementInProgress,
            ChinaWorkflowStage::PartiallyReceived,
        ], true)) {
            return 'Fulfilment is not eligible for goods receipt.';
        }

        return null;
    }

    private function resolveReceivablePurchaseOrder(Fulfillment $fulfillment): ?PurchaseOrder
    {
        $orders = $fulfillment->relationLoaded('purchaseOrders')
            ? $fulfillment->purchaseOrders
            : $fulfillment->purchaseOrders()->with('items')->get();

        foreach ($orders as $purchaseOrder) {
            if ($this->isReceivablePurchaseOrder($purchaseOrder)) {
                return $purchaseOrder;
            }
        }

        return null;
    }

    private function isReceivablePurchaseOrder(PurchaseOrder $purchaseOrder): bool
    {
        $status = $purchaseOrder->status instanceof PurchaseOrderStatus
            ? $purchaseOrder->status
            : PurchaseOrderStatus::tryFrom((string) ($purchaseOrder->status ?? ''));

        if ($status === null || ! $status->canReceive()) {
            return false;
        }

        $response = $purchaseOrder->supplier_response instanceof SupplierPoResponse
            ? $purchaseOrder->supplier_response
            : SupplierPoResponse::tryFrom((string) ($purchaseOrder->supplier_response ?? ''));

        if (! in_array($response, [SupplierPoResponse::Accepted, SupplierPoResponse::PartiallyAccepted], true)) {
            return false;
        }

        $items = $purchaseOrder->relationLoaded('items')
            ? $purchaseOrder->items
            : $purchaseOrder->items()->get();

        foreach ($items as $item) {
            if ($item->quantityOutstanding() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{purchase_order_item_id: string, quantity: int}>
     */
    private function buildFullReceiveItems(PurchaseOrder $purchaseOrder): array
    {
        $items = $purchaseOrder->relationLoaded('items')
            ? $purchaseOrder->items
            : $purchaseOrder->items()->get();

        $lines = [];

        foreach ($items as $item) {
            $outstanding = $item->quantityOutstanding();
            if ($outstanding > 0) {
                $lines[] = [
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $outstanding,
                ];
            }
        }

        return $lines;
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processMarkQcPassed(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order', 'chinaWorkflowRecord'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveMarkQcPassedIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        $order = $fulfillment->order;
        if ($order === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Order not found.',
            ];
        }

        try {
            $this->chinaWorkflowEngine->recordQc(
                $order,
                ChinaQcStatus::Passed,
                null,
                $admin,
            );

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to record QC pass.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to record QC pass.',
            ];
        }
    }

    private function resolveMarkQcPassedIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return 'Only China Import fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return 'Fulfilment is already completed or cancelled.';
        }

        $record = $fulfillment->chinaWorkflowRecord;
        if ($record === null) {
            return 'China workflow record not found.';
        }

        $qcStatus = $record->qc_status instanceof ChinaQcStatus
            ? $record->qc_status
            : ChinaQcStatus::tryFrom((string) ($record->qc_status ?? ''));

        if ($qcStatus === ChinaQcStatus::Passed) {
            return 'QC already passed.';
        }

        $stage = $record->stage instanceof ChinaWorkflowStage
            ? $record->stage
            : ChinaWorkflowStage::tryFrom((string) ($record->stage ?? ''));

        if ($stage === ChinaWorkflowStage::QcPassed) {
            return 'QC already passed.';
        }

        if ($stage === null) {
            return 'Goods have not been received.';
        }

        if (! in_array($stage, [
            ChinaWorkflowStage::Received,
            ChinaWorkflowStage::PartiallyReceived,
            ChinaWorkflowStage::QcPending,
        ], true)) {
            if (in_array($stage, [
                ChinaWorkflowStage::AwaitingProcurement,
                ChinaWorkflowStage::ProcurementInProgress,
            ], true)) {
                return 'Goods have not been received.';
            }

            return 'Fulfilment is not ready for QC.';
        }

        if ($qcStatus !== null && $qcStatus !== ChinaQcStatus::Pending) {
            return 'Fulfilment is not ready for QC.';
        }

        return null;
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processMarkChinaPackingComplete(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['warehouseJob', 'chinaWorkflowRecord'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveMarkChinaPackingCompleteIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        $job = $fulfillment->warehouseJob;
        if ($job === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Warehouse job not found.',
            ];
        }

        try {
            $current = $job->status instanceof WarehouseJobStatus
                ? $job->status
                : WarehouseJobStatus::tryFrom((string) ($job->status ?? ''));

            if ($current === null) {
                return [
                    'fulfillment_id' => $fulfillmentId,
                    'success' => false,
                    'error' => 'Warehouse job status is invalid.',
                ];
            }

            while ($current !== WarehouseJobStatus::Packed) {
                $next = $current->nextForward();
                if ($next === null || $next === WarehouseJobStatus::ReadyToShip) {
                    return [
                        'fulfillment_id' => $fulfillmentId,
                        'success' => false,
                        'error' => 'Unable to complete warehouse packing.',
                    ];
                }

                $this->assertWarehouseStatusPermission($admin, $next);

                $job = $this->warehouseEngine->updateStatus($job, [
                    'status' => $next->value,
                ]);
                $job->refresh();
                $current = $job->status instanceof WarehouseJobStatus
                    ? $job->status
                    : WarehouseJobStatus::tryFrom((string) ($job->status ?? ''));
            }

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to complete warehouse packing.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to complete warehouse packing.',
            ];
        }
    }

    private function resolveMarkChinaPackingCompleteIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return 'Only China Import fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return 'Fulfilment is already completed or cancelled.';
        }

        $record = $fulfillment->chinaWorkflowRecord;
        if ($record === null) {
            return 'China workflow record not found.';
        }

        $qcStatus = $record->qc_status instanceof ChinaQcStatus
            ? $record->qc_status
            : ChinaQcStatus::tryFrom((string) ($record->qc_status ?? ''));

        if ($qcStatus !== ChinaQcStatus::Passed) {
            return 'QC must be passed before warehouse packing.';
        }

        $stage = $record->stage instanceof ChinaWorkflowStage
            ? $record->stage
            : ChinaWorkflowStage::tryFrom((string) ($record->stage ?? ''));

        if ($stage === null || ! in_array($stage, [
            ChinaWorkflowStage::QcPassed,
            ChinaWorkflowStage::Consolidating,
            ChinaWorkflowStage::Consolidated,
            ChinaWorkflowStage::ExportReady,
            ChinaWorkflowStage::CompanyShippingReady,
        ], true)) {
            return 'Fulfilment is not ready for warehouse preparation.';
        }

        $warehouseStatus = $fulfillment->warehouseJob?->status;
        $warehouse = $warehouseStatus instanceof WarehouseJobStatus
            ? $warehouseStatus
            : WarehouseJobStatus::tryFrom((string) ($warehouseStatus ?? ''));

        if ($warehouse === WarehouseJobStatus::Packed) {
            return 'Warehouse packing is already complete.';
        }

        if ($warehouse === WarehouseJobStatus::ReadyToShip) {
            return 'Warehouse is already ready to ship.';
        }

        if ($warehouse === WarehouseJobStatus::Cancelled) {
            return 'Warehouse job is cancelled.';
        }

        if (! in_array($warehouse, [
            WarehouseJobStatus::Pending,
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
        ], true)) {
            return 'Fulfilment is not ready for warehouse preparation.';
        }

        return null;
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processMarkExportReady(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'chinaWorkflowRecord', 'warehouseJob'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return BulkActionResult::skipped($fulfillmentId, 'FULFILLMENT_NOT_FOUND', 'Fulfilment not found.');
        }

        $ineligibility = $this->resolveMarkExportReadyIneligibility($fulfillment);
        if ($ineligibility !== null) {
            return BulkActionResult::skipped(
                $fulfillmentId,
                $ineligibility['code'],
                $ineligibility['reason'],
            );
        }

        $order = $fulfillment->order;
        if ($order === null) {
            return BulkActionResult::skipped($fulfillmentId, 'ORDER_NOT_FOUND', 'Order not found.');
        }

        try {
            $this->chinaWorkflowEngine->markExportReady($order, $admin, $this->defaultExportReadyChecklist());

            return BulkActionResult::succeeded($fulfillmentId);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return BulkActionResult::failed(
                $fulfillmentId,
                'VALIDATION_FAILED',
                is_string($message) ? $message : 'Unable to mark export ready.',
            );
        } catch (\Throwable $exception) {
            return BulkActionResult::failed(
                $fulfillmentId,
                'VALIDATION_FAILED',
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to mark export ready.',
            );
        }
    }

    /**
     * @return array{code: string, reason: string}|null
     */
    private function resolveMarkExportReadyIneligibility(Fulfillment $fulfillment): ?array
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return [
                'code' => 'WRONG_STRATEGY',
                'reason' => 'Only China Import fulfilments can use this bulk action.',
            ];
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return [
                'code' => 'ALREADY_COMPLETED',
                'reason' => 'Fulfilment is already completed or cancelled.',
            ];
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        $delivery = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        if ($delivery === DeliveryType::CustomerAgent) {
            return [
                'code' => 'WRONG_DELIVERY_TYPE',
                'reason' => 'Customer agent orders cannot be marked export ready.',
            ];
        }

        if ($delivery !== DeliveryType::CompanyShipping) {
            return [
                'code' => 'WRONG_DELIVERY_TYPE',
                'reason' => 'Only company shipping China orders can use this bulk action.',
            ];
        }

        $record = $fulfillment->chinaWorkflowRecord;
        if ($record === null) {
            return [
                'code' => 'NOT_ELIGIBLE_STAGE',
                'reason' => 'China workflow record not found.',
            ];
        }

        if ($record->isAuthoritativelyExportReady()) {
            return [
                'code' => 'ALREADY_EXPORT_READY',
                'reason' => 'Export is already marked ready.',
            ];
        }

        $warehouseStatus = $fulfillment->warehouseJob?->status;
        $warehouse = $warehouseStatus instanceof WarehouseJobStatus
            ? $warehouseStatus
            : WarehouseJobStatus::tryFrom((string) ($warehouseStatus ?? ''));

        if ($warehouse !== WarehouseJobStatus::ReadyToShip) {
            return [
                'code' => 'WAREHOUSE_NOT_READY',
                'reason' => 'Warehouse must be ready to ship before export readiness.',
            ];
        }

        $order = $fulfillment->order;
        if ($order === null) {
            return [
                'code' => 'ORDER_NOT_FOUND',
                'reason' => 'Order not found.',
            ];
        }

        $blockers = $this->chinaWorkflowEngine->evaluateExportReadinessBlockers(
            $record,
            $order,
            $this->defaultExportReadyChecklist(),
        );

        if ($blockers !== []) {
            return [
                'code' => $this->mapExportReadinessBlockerToReasonCode($blockers[0]),
                'reason' => $blockers[0],
            ];
        }

        return null;
    }

    private function mapExportReadinessBlockerToReasonCode(string $blocker): string
    {
        $message = strtolower($blocker);

        if (str_contains($message, 'qc')) {
            return 'NOT_ELIGIBLE_QC';
        }

        if (str_contains($message, 'workflow stage') || str_contains($message, 'consolidation')) {
            return 'NOT_ELIGIBLE_STAGE';
        }

        if (str_contains($message, 'warehouse packing') || str_contains($message, 'packed')) {
            return 'WAREHOUSE_NOT_READY';
        }

        if (str_contains($message, 'checklist')) {
            return 'CHECKLIST_INCOMPLETE';
        }

        return 'NOT_ELIGIBLE';
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processMarkAgentDelivered(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'chinaWorkflowRecord', 'warehouseJob'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveMarkAgentDeliveredIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        $order = $fulfillment->order;
        if ($order === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Order not found.',
            ];
        }

        try {
            $this->ensureCustomerAgentAuthorizedForHandover($order, $admin);
            $this->customerAgentWorkflowEngine->completeHandover($order, $admin);

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to deliver to customer agent.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to deliver to customer agent.',
            ];
        }
    }

    private function processMarkCustomerCollected(Admin $admin, string $fulfillmentId): array
    {
        return $this->processCompanyShippingHandover(
            $admin,
            $fulfillmentId,
            LastMileReceivingMethod::SelfPickup,
        );
    }

    private function processMarkCustomerDelivered(Admin $admin, string $fulfillmentId): array
    {
        return $this->processCompanyShippingHandover(
            $admin,
            $fulfillmentId,
            LastMileReceivingMethod::NegotiatedDelivery,
        );
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool, reason_code?: string}
     */
    private function processCompanyShippingHandover(
        Admin $admin,
        string $fulfillmentId,
        LastMileReceivingMethod $expectedMethod,
    ): array {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'shipment'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return BulkActionResult::skipped(
                $fulfillmentId,
                'FULFILLMENT_NOT_FOUND',
                'Fulfilment not found.',
            );
        }

        try {
            if ($expectedMethod === LastMileReceivingMethod::SelfPickup) {
                $this->companyShippingHandoverService->completePickup($fulfillment, $admin);
            } else {
                $this->companyShippingHandoverService->completeDelivery($fulfillment, $admin);
            }

            return BulkActionResult::succeeded($fulfillmentId);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return BulkActionResult::skipped(
                $fulfillmentId,
                $this->companyShippingHandoverService->mapValidationToBulkReasonCode($exception),
                is_string($message) ? $message : 'Not eligible for company shipping handover.',
            );
        } catch (\Throwable $exception) {
            return BulkActionResult::failed(
                $fulfillmentId,
                'VALIDATION_FAILED',
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to complete company shipping handover.',
            );
        }
    }

    private function resolveMarkAgentDeliveredIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return 'Only China Import fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return 'Fulfilment is already completed or cancelled.';
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        $delivery = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        if ($delivery !== DeliveryType::CustomerAgent) {
            return 'Only customer agent China orders can use this bulk action.';
        }

        $pickup = CustomerAgentPickup::query()
            ->where('order_id', $fulfillment->order_id)
            ->first();

        if ($pickup !== null
            && ($pickup->handover_completed_at !== null
                || $pickup->pickup_status === AgentPickupStatus::HandoverCompleted)
        ) {
            return 'Agent delivery is already completed.';
        }

        $evaluation = $this->shipmentEligibilityService->evaluateCustomerAgentPickup(
            $fulfillment,
            requireAuthorization: false,
        );

        if (! $evaluation['eligible']) {
            return is_string($evaluation['reason'] ?? null)
                ? $evaluation['reason']
                : 'Customer agent delivery prerequisites not met.';
        }

        return null;
    }

    private function ensureCustomerAgentAuthorizedForHandover(Order $order, Admin $admin): void
    {
        $pickup = CustomerAgentPickup::query()
            ->where('order_id', $order->id)
            ->first();

        if ($pickup !== null && $pickup->hasValidAuthorization()) {
            return;
        }

        $input = [];
        if ($pickup !== null && in_array($pickup->authorization_status, [
            PickupAuthorizationStatus::Rejected,
            PickupAuthorizationStatus::Revoked,
        ], true)) {
            $input['reissue'] = true;
        }

        $this->customerAgentWorkflowEngine->authorize($order, $admin, $input);
    }

    /**
     * @return array{fulfillment_id: string, success: bool, error?: string, skipped?: bool}
     */
    private function processCreateShipment(Admin $admin, string $fulfillmentId): array
    {
        $fulfillment = Fulfillment::query()
            ->with(['order.deliveryOption', 'shipment', 'warehouseJob', 'chinaWorkflowRecord'])
            ->find($fulfillmentId);

        if ($fulfillment === null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => 'Fulfilment not found.',
            ];
        }

        $ineligibleReason = $this->resolveCreateShipmentIneligibility($fulfillment);
        if ($ineligibleReason !== null) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'skipped' => true,
                'error' => $ineligibleReason,
            ];
        }

        try {
            $this->shipmentEngine->createForFulfillment($fulfillment);

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => true,
            ];
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => is_string($message) ? $message : 'Unable to create shipment.',
            ];
        } catch (\Throwable $exception) {
            return [
                'fulfillment_id' => $fulfillmentId,
                'success' => false,
                'error' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to create shipment.',
            ];
        }
    }

    private function resolveCreateShipmentIneligibility(Fulfillment $fulfillment): ?string
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return 'Only China Import fulfilments can use this bulk action.';
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($status !== null && $status->isTerminal()) {
            return 'Fulfilment is already completed or cancelled.';
        }

        if ($status === FulfillmentStatus::Shipped) {
            return 'Fulfilment is already shipped.';
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        $delivery = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        if ($delivery !== DeliveryType::CompanyShipping) {
            return 'Only company shipping China orders can use this bulk action.';
        }

        if ($fulfillment->shipment !== null) {
            return 'Shipment already exists for this fulfillment.';
        }

        $evaluation = $this->shipmentEligibilityService->evaluate($fulfillment);

        if (! $evaluation['eligible']) {
            return is_string($evaluation['reason'] ?? null)
                ? $evaluation['reason']
                : 'Fulfillment is not eligible for shipment.';
        }

        return null;
    }

    /**
     * @return array{
     *     commercial_invoice: bool,
     *     packing_list: bool,
     *     customs_docs: bool,
     *     weight_confirmed: bool,
     *     dimensions_confirmed: bool
     * }
     */
    private function defaultExportReadyChecklist(): array
    {
        return [
            'commercial_invoice' => true,
            'packing_list' => true,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ];
    }

    private function assertWarehouseStatusPermission(Admin $admin, WarehouseJobStatus $target): void
    {
        $permission = $target === WarehouseJobStatus::ReadyToShip
            ? AdminPermissions::WAREHOUSE_JOBS_COMPLETE
            : AdminPermissions::WAREHOUSE_JOBS_UPDATE;

        if (! $admin->hasAdminPermission($permission)) {
            throw ValidationException::withMessages([
                'permission' => ['You do not have permission to advance warehouse preparation.'],
            ]);
        }
    }

    private function recordBulkAudit(
        Admin $admin,
        string $batchId,
        string $actionKey,
        int $requested,
        int $succeeded,
        int $failed,
        int $skipped,
    ): void {
        try {
            $this->activityLogger->write(FulfillmentBulkActionCompleted::record(
                admin: $admin,
                batchId: $batchId,
                actionKey: $actionKey,
                requestedCount: $requested,
                succeededCount: $succeeded,
                failedCount: $failed,
                skippedCount: $skipped,
            ));
        } catch (\Throwable) {
            // Audit failure must not roll back successful bulk operations.
        }
    }
}
