<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\CancelCheckoutSessionAction;
use App\Actions\Checkout\ApplyCheckoutShippingChoiceAction;
use App\Actions\Checkout\PrepareCheckoutAction;
use App\Actions\Checkout\RefreshCheckoutSessionAction;
use App\Actions\Checkout\ShowCheckoutAction;
use App\Actions\Checkout\ShowCheckoutSessionAction;
use App\Actions\Checkout\StartCheckoutSessionAction;
use App\Http\Requests\Checkout\ApplyCheckoutShippingChoiceRequest;
use App\Http\Requests\Checkout\StartCheckoutSessionRequest;
use App\Http\Resources\CheckoutResource;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function show(ShowCheckoutAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new CheckoutResource($action->handle($user)),
        );
    }

    public function prepare(PrepareCheckoutAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Preview / address validation only. Order creation uses POST /checkout/start
        // then POST /orders/from-checkout/{session} (or compatibility POST /orders/confirm).
        return ApiResponse::success(
            data: new CheckoutResource($action->handle($user)),
            message: 'Checkout prepared successfully.',
        );
    }

    public function start(StartCheckoutSessionRequest $request, StartCheckoutSessionAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new CheckoutSessionResource($action->handle($user, $request->validated())),
            message: 'Checkout session started.',
            status: 201,
        );
    }

    public function showSession(
        CheckoutSession $checkoutSession,
        ShowCheckoutSessionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new CheckoutSessionResource($action->handle($user, $checkoutSession)),
        );
    }

    public function refresh(
        CheckoutSession $checkoutSession,
        RefreshCheckoutSessionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new CheckoutSessionResource($action->handle($user, $checkoutSession)),
            message: 'Checkout session refreshed.',
        );
    }

    public function applyShippingChoice(
        CheckoutSession $checkoutSession,
        ApplyCheckoutShippingChoiceRequest $request,
        ApplyCheckoutShippingChoiceAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new CheckoutSessionResource(
                $action->handle($user, $checkoutSession, $request->validated()),
            ),
            message: 'Shipping choice saved.',
        );
    }

    public function destroySession(
        CheckoutSession $checkoutSession,
        CancelCheckoutSessionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $action->handle($user, $checkoutSession);

        return ApiResponse::success(
            message: 'Checkout session cancelled.',
        );
    }
}
