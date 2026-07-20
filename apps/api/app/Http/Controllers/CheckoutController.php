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
use App\Http\Resources\CheckoutResource;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function show(ShowCheckoutAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CheckoutResource($action->handle($user)),
        ]);
    }

    public function prepare(PrepareCheckoutAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Preview / address validation only. Order creation uses POST /checkout/start
        // then POST /orders/from-checkout/{session} (or compatibility POST /orders/confirm).
        return response()->json([
            'success' => true,
            'message' => 'Checkout prepared successfully.',
            'data' => new CheckoutResource($action->handle($user)),
        ]);
    }

    public function start(StartCheckoutSessionAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Checkout session started.',
            'data' => new CheckoutSessionResource($action->handle($user)),
        ], 201);
    }

    public function showSession(
        CheckoutSession $checkoutSession,
        ShowCheckoutSessionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CheckoutSessionResource($action->handle($user, $checkoutSession)),
        ]);
    }

    public function refresh(
        CheckoutSession $checkoutSession,
        RefreshCheckoutSessionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Checkout session refreshed.',
            'data' => new CheckoutSessionResource($action->handle($user, $checkoutSession)),
        ]);
    }

    public function applyShippingChoice(
        CheckoutSession $checkoutSession,
        ApplyCheckoutShippingChoiceRequest $request,
        ApplyCheckoutShippingChoiceAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Shipping choice saved.',
            'data' => new CheckoutSessionResource(
                $action->handle($user, $checkoutSession, $request->validated()),
            ),
        ]);
    }

    public function destroySession(
        CheckoutSession $checkoutSession,
        CancelCheckoutSessionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $action->handle($user, $checkoutSession);

        return response()->json([
            'success' => true,
            'message' => 'Checkout session cancelled.',
        ]);
    }
}
