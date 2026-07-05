<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminOrders\CancelOrderAction;
use App\Actions\AdminOrders\CreateOrderAction;
use App\Actions\AdminOrders\GetAdminOrdersAction;
use App\Actions\AdminOrders\PayOrderAction;
use App\Actions\AdminOrders\ShowOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminOrdersRequest;
use App\Http\Requests\Admin\ShowOrderRequest;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderController extends Controller
{
    public function index(
        IndexAdminOrdersRequest $request,
        GetAdminOrdersAction $action,
    ): AnonymousResourceCollection {
        return OrderResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new OrderResource($action->handle($request)),
        ], 201);
    }

    public function show(
        ShowOrderRequest $request,
        Order $order,
        ShowOrderAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new OrderResource($action->handle($order)),
        ]);
    }

    public function pay(Order $order, PayOrderAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order paid successfully.',
            'data' => new OrderResource($action->handle($order)),
        ]);
    }

    public function cancel(Order $order, CancelOrderAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => new OrderResource($action->handle($order)),
        ]);
    }
}
