<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexFulfillmentAssigneesRequest;
use App\Http\Requests\Admin\IndexFulfillmentsRequest;
use App\Http\Requests\Admin\UpdateFulfillmentAssignmentRequest;
use App\Http\Requests\Admin\UpdateFulfillmentBulkAssignmentRequest;
use App\Http\Requests\Admin\UpdateFulfillmentStatusRequest;
use App\Http\Resources\FulfillmentOperationalReadModelResource;
use App\Http\Resources\FulfillmentResource;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\Fulfillment\CompanyShippingHandoverService;
use App\Services\Fulfillment\FulfillmentAssigneeQuery;
use App\Services\Fulfillment\FulfillmentBulkAssignmentService;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\FulfillmentOperationalReadModelBuilder;
use App\Services\Fulfillment\FulfillmentStatusUpdateContext;
use App\Services\Fulfillment\LocalFulfillmentCompletionService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminFulfillmentController extends Controller
{
    public function index(IndexFulfillmentsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $query = Fulfillment::query()
            ->with(['order.user', 'order.items', 'order.deliveryOption', 'warehouseJob', 'assignee', 'chinaWorkflowRecord', 'shipment', 'purchaseOrders.items'])
            ->withExists([
                'purchaseOrders as has_active_purchase_orders' => static fn ($query) => $query->where(
                    'status',
                    '!=',
                    PurchaseOrderStatus::Cancelled,
                ),
            ])
            ->latest();

        if ($strategy = $request->validated('strategy')) {
            $query->where('strategy', $strategy);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($orderId = $request->validated('order_id')) {
            $query->where('order_id', $orderId);
        }

        return FulfillmentResource::collection($query->paginate($perPage))
            ->additional(['success' => true]);
    }

    public function assignees(
        IndexFulfillmentAssigneesRequest $request,
        FulfillmentAssigneeQuery $assignees,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $assignees->list(),
        ]);
    }

    public function create(Order $order, FulfillmentEngine $engine): JsonResponse
    {
        $this->authorize(AdminPermissions::ORDERS_FULFILL);

        $fulfillment = $engine->createForOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment created.',
            'data' => new FulfillmentResource($fulfillment),
        ], 201);
    }

    public function show(Fulfillment $fulfillment, FulfillmentEngine $engine): JsonResponse
    {
        $this->authorize(AdminPermissions::ORDERS_VIEW);

        return response()->json([
            'success' => true,
            'data' => new FulfillmentResource($engine->show($fulfillment)),
        ]);
    }

    public function updateStatus(
        Fulfillment $fulfillment,
        UpdateFulfillmentStatusRequest $request,
        FulfillmentEngine $engine,
    ): JsonResponse {
        /** @var Admin|null $admin */
        $admin = Auth::user();

        $validated = $request->validated();
        $this->assertAdminDeliveredTransitionAllowed($fulfillment, $validated);

        $updated = $engine->updateStatus(
            $fulfillment,
            $validated,
            new FulfillmentStatusUpdateContext(
                source: FulfillmentStatusHistorySource::Admin,
                admin: $admin instanceof Admin ? $admin : null,
            ),
        );

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment status updated.',
            'data' => new FulfillmentResource($updated),
        ]);
    }

    public function updateAssignment(
        Fulfillment $fulfillment,
        UpdateFulfillmentAssignmentRequest $request,
        FulfillmentEngine $engine,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::user();

        $updated = $engine->assign(
            $fulfillment,
            $request->validated('assigned_to'),
            $admin,
        );

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment assignment updated.',
            'data' => new FulfillmentResource($updated),
        ]);
    }

    public function bulkAssignment(
        UpdateFulfillmentBulkAssignmentRequest $request,
        FulfillmentBulkAssignmentService $bulkAssignment,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::user();

        $result = $bulkAssignment->assign(
            $admin,
            $request->validated('fulfillment_ids'),
            $request->validated('assigned_to'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment assignments updated.',
            'data' => $result,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertAdminDeliveredTransitionAllowed(Fulfillment $fulfillment, array $validated): void
    {
        if (! array_key_exists('status', $validated)) {
            return;
        }

        $targetStatus = FulfillmentStatus::tryFrom((string) $validated['status']);
        if ($targetStatus !== FulfillmentStatus::Delivered) {
            return;
        }

        $fulfillment->loadMissing('order.deliveryOption');
        $currentStatus = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($currentStatus === FulfillmentStatus::Delivered) {
            return;
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        $deliveryType = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        $message = match ($deliveryType) {
            DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery => 'Use POST /admin/fulfillments/{fulfillment}/complete-local to mark Buy From TZ orders completed.',
            DeliveryType::CustomerAgent => 'Use POST /admin/orders/{order}/customer-agent/handover to complete customer agent delivery.',
            DeliveryType::CompanyShipping => 'Use the company handover pickup or delivery completion endpoints to finish company shipping orders.',
            default => 'Direct delivered status is not allowed. Use the appropriate completion action for this delivery type.',
        };

        throw ValidationException::withMessages([
            'status' => [$message],
        ]);
    }

    public function completeLocal(
        Fulfillment $fulfillment,
        LocalFulfillmentCompletionService $completion,
    ): JsonResponse {
        $this->authorize(AdminPermissions::ORDERS_FULFILL);

        /** @var Admin|null $admin */
        $admin = Auth::user();

        $updated = $completion->complete(
            $fulfillment,
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Buy From TZ order marked completed.',
            'data' => new FulfillmentResource($updated),
        ]);
    }

    public function completeCompanyHandoverPickup(
        Fulfillment $fulfillment,
        CompanyShippingHandoverService $handover,
    ): JsonResponse {
        $this->authorize(AdminPermissions::ORDERS_FULFILL);

        /** @var Admin|null $admin */
        $admin = Auth::user();

        $updated = $handover->completePickup(
            $fulfillment,
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer collection confirmed and order completed.',
            'data' => new FulfillmentResource($updated),
        ]);
    }

    public function completeCompanyHandoverDelivery(
        Fulfillment $fulfillment,
        CompanyShippingHandoverService $handover,
    ): JsonResponse {
        $this->authorize(AdminPermissions::ORDERS_FULFILL);

        /** @var Admin|null $admin */
        $admin = Auth::user();

        $updated = $handover->completeDelivery(
            $fulfillment,
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer delivery confirmed and order completed.',
            'data' => new FulfillmentResource($updated),
        ]);
    }

    public function operationalView(
        Fulfillment $fulfillment,
        FulfillmentOperationalReadModelBuilder $builder,
    ): JsonResponse {
        $this->authorize(AdminPermissions::ORDERS_VIEW);

        return response()->json([
            'success' => true,
            'data' => new FulfillmentOperationalReadModelResource($builder->build($fulfillment)),
        ]);
    }
}
