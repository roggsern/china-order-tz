<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminOrders\CancelOrderAction;
use App\Actions\AdminOrders\CompleteCancellationRefundAction;
use App\Actions\AdminOrders\CreateOrderAction;
use App\Actions\AdminOrders\FailCancellationRefundAction;
use App\Actions\AdminOrders\GetAdminOrdersAction;
use App\Actions\AdminOrders\PayOrderAction;
use App\Actions\AdminOrders\ShowOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteCancellationRefundRequest;
use App\Http\Requests\Admin\FailCancellationRefundRequest;
use App\Http\Requests\Admin\IndexAdminOrdersRequest;
use App\Http\Requests\Admin\ShowOrderRequest;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RefundTransactionResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderController extends Controller
{
    public function index(
        IndexAdminOrdersRequest $request,
        GetAdminOrdersAction $action,
    ): AnonymousResourceCollection {
        return OrderResource::collection($action->handle($request->validated('status')))
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

    public function completeCancellationRefund(
        Order $order,
        CompleteCancellationRefundRequest $request,
        CompleteCancellationRefundAction $action,
    ): JsonResponse {
        $refund = $action->handle($order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cancellation refund recorded. Order marked refunded.',
            'data' => [
                'refund' => new RefundTransactionResource($refund->loadMissing(['order', 'returnRequest'])),
                'order' => new OrderResource($order->fresh([
                    'user',
                    'items',
                    'payments',
                    'statusHistory',
                    'refundTransactions',
                ]) ?? $order),
            ],
        ]);
    }

    public function failCancellationRefund(
        Order $order,
        FailCancellationRefundRequest $request,
        FailCancellationRefundAction $action,
    ): JsonResponse {
        $refund = $action->handle($order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cancellation refund marked failed. Order remains refund_pending.',
            'data' => [
                'refund' => new RefundTransactionResource($refund->loadMissing(['order', 'returnRequest'])),
                'order' => new OrderResource($order->fresh([
                    'user',
                    'payments',
                    'statusHistory',
                    'refundTransactions',
                ]) ?? $order),
            ],
        ]);
    }
}
