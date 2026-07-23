<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceivePurchaseOrderRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Requests\Admin\UpdatePurchaseOrderStatusRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\ReceivingRecordResource;
use App\Models\Admin;
use App\Models\PurchaseOrder;
use App\Services\Procurement\PurchaseOrderEngine;
use App\Services\Procurement\ReceivingEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderEngine $purchaseOrders,
        private readonly ReceivingEngine $receiving,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::PURCHASE_ORDERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return PurchaseOrderResource::collection(
            $this->purchaseOrders->paginate(
                $request->only(['status', 'supplier_id', 'search']),
                $perPage,
            )
        )->additional(['success' => true]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        $order = $this->purchaseOrders->create(
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize(AdminPermissions::PURCHASE_ORDERS_VIEW);

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($this->purchaseOrders->show($purchaseOrder)),
        ]);
    }

    public function updateStatus(
        PurchaseOrder $purchaseOrder,
        UpdatePurchaseOrderStatusRequest $request,
    ): JsonResponse {
        $admin = auth('sanctum')->user();
        $updated = $this->purchaseOrders->updateStatus(
            $purchaseOrder,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Purchase order status updated.',
            'data' => new PurchaseOrderResource($updated),
        ]);
    }

    public function receive(
        PurchaseOrder $purchaseOrder,
        ReceivePurchaseOrderRequest $request,
    ): JsonResponse {
        $admin = auth('sanctum')->user();
        $record = $this->receiving->receive(
            $purchaseOrder,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Goods received and inventory updated.',
            'data' => new ReceivingRecordResource($record),
            'purchase_order' => new PurchaseOrderResource(
                $this->purchaseOrders->show($purchaseOrder->fresh() ?? $purchaseOrder)
            ),
        ], 201);
    }
}
