<?php

namespace App\Http\Controllers;

use App\Actions\CustomerOrders\ConfirmCheckoutAction;
use App\Actions\CustomerOrders\ListCustomerOrdersAction;
use App\Actions\CustomerOrders\PrepareOrderPaymentAction;
use App\Actions\CustomerOrders\ShowCustomerOrderAction;
use App\Actions\CustomerOrders\ShowOrderPaymentAction;
use App\Actions\CustomerOrders\ShowShipmentTrackingAction;
use App\Http\Requests\CustomerOrders\IndexCustomerOrdersRequest;
use App\Http\Requests\Orders\PrepareOrderPaymentRequest;
use App\Http\Resources\CustomerOrderDetailResource;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Resources\OrderConfirmationResource;
use App\Http\Resources\PaymentPreparationResource;
use App\Http\Resources\ShipmentTrackingResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerOrderController extends Controller
{
    public function index(
        IndexCustomerOrdersRequest $request,
        ListCustomerOrdersAction $action,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = auth()->user();

        return CustomerOrderResource::collection(
            $action->handle(
                $user,
                (int) $request->validated('per_page', 10),
                $request->validated('filter', 'all'),
            )
        )->additional(['success' => true]);
    }

    public function confirm(ConfirmCheckoutAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => new OrderConfirmationResource($action->handle($user)),
        ], 201);
    }

    public function storePayment(
        Order $order,
        PrepareOrderPaymentRequest $request,
        PrepareOrderPaymentAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Payment prepared successfully.',
            'data' => new PaymentPreparationResource($action->handle($order, $request, $user)),
        ], 201);
    }

    public function showPayment(Order $order, ShowOrderPaymentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new PaymentPreparationResource($action->handle($order, $user)),
        ]);
    }

    public function show(Order $order, ShowCustomerOrderAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CustomerOrderDetailResource($action->handle($order, $user)),
        ]);
    }

    public function tracking(Order $order, ShowShipmentTrackingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new ShipmentTrackingResource($action->handle($order, $user)),
        ]);
    }
}
