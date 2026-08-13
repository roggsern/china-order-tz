<?php

namespace App\Http\Controllers;

use App\Actions\CustomerOrders\CancelCustomerOrderAction;
use App\Actions\CustomerOrders\ConfirmCheckoutAction;
use App\Actions\CustomerOrders\ListCustomerOrdersAction;
use App\Actions\CustomerOrders\PrepareOrderPaymentAction;
use App\Actions\CustomerOrders\ShowCustomerOrderAction;
use App\Actions\CustomerOrders\ShowOrderPaymentAction;
use App\Actions\CustomerOrders\ShowShipmentTrackingAction;
use App\Actions\Orders\CreateOrderFromCheckoutAction;
use App\Http\Requests\CustomerOrders\ConfirmCheckoutRequest;
use App\Http\Requests\CustomerOrders\IndexCustomerOrdersRequest;
use App\Http\Requests\Orders\PrepareOrderPaymentRequest;
use App\Http\Resources\CustomerOrderDetailResource;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Resources\OrderConfirmationResource;
use App\Http\Resources\OrderEngineResource;
use App\Http\Resources\PaymentPreparationResource;
use App\Http\Resources\ShipmentTrackingResource;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function index(
        IndexCustomerOrdersRequest $request,
        ListCustomerOrdersAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        // Preserve Laravel paginator `data` / `links` / `meta` shape used by the web app.
        $payload = CustomerOrderResource::collection(
            $action->handle(
                $user,
                (int) $request->validated('per_page', 10),
                $request->validated('filter', 'all'),
            )
        )->toResponse($request)->getData(true);

        return ApiResponse::success(
            data: $payload['data'] ?? [],
            meta: is_array($payload['meta'] ?? null) ? $payload['meta'] : null,
            extra: array_filter([
                'links' => $payload['links'] ?? null,
            ], static fn ($value) => $value !== null),
        );
    }

    public function confirm(
        ConfirmCheckoutRequest $request,
        ConfirmCheckoutAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new OrderConfirmationResource($action->handle($user, $request->validated())),
            message: 'Order created successfully.',
            status: 201,
        );
    }

    public function fromCheckout(
        CheckoutSession $checkoutSession,
        CreateOrderFromCheckoutAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new OrderEngineResource($action->handle($user, $checkoutSession)),
            message: 'Order created from checkout session.',
            status: 201,
        );
    }

    public function storePayment(
        Order $order,
        PrepareOrderPaymentRequest $request,
        PrepareOrderPaymentAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new PaymentPreparationResource($action->handle($order, $request, $user)),
            message: 'Payment prepared successfully.',
            status: 201,
        );
    }

    public function showPayment(Order $order, ShowOrderPaymentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new PaymentPreparationResource($action->handle($order, $user)),
        );
    }

    public function show(Order $order, ShowCustomerOrderAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new CustomerOrderDetailResource($action->handle($order, $user)),
        );
    }

    public function cancel(Order $order, CancelCustomerOrderAction $action, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $reason = $request->input('reason');

        return ApiResponse::success(
            data: new CustomerOrderDetailResource(
                $action->handle($user, $order, is_string($reason) ? $reason : null),
            ),
            message: 'Order cancellation recorded.',
        );
    }

    public function tracking(Order $order, ShowShipmentTrackingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new ShipmentTrackingResource($action->handle($order, $user)),
        );
    }
}
