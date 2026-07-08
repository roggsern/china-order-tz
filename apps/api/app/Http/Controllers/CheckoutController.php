<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\PrepareCheckoutAction;
use App\Actions\Checkout\ShowCheckoutAction;
use App\Http\Resources\CheckoutResource;
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

        return response()->json([
            'success' => true,
            'message' => 'Checkout prepared successfully.',
            'data' => new CheckoutResource($action->handle($user)),
        ]);
    }
}
