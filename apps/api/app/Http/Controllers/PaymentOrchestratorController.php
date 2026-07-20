<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RefreshPaymentTransactionAction;
use App\Actions\Payments\ShowPaymentTransactionAction;
use App\Actions\Payments\StartPaymentTransactionAction;
use App\Http\Requests\Payments\StartPaymentTransactionRequest;
use App\Http\Resources\PaymentTransactionResource;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PaymentOrchestratorController extends Controller
{
    public function start(
        Order $order,
        StartPaymentTransactionRequest $request,
        StartPaymentTransactionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $transaction = $action->handle(
            $user,
            $order,
            $request->validated('provider'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment transaction started.',
            'data' => new PaymentTransactionResource($transaction->load('order')),
        ], 201);
    }

    public function show(
        PaymentTransaction $paymentTransaction,
        ShowPaymentTransactionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new PaymentTransactionResource(
                $action->handle($user, $paymentTransaction)->load('order'),
            ),
        ]);
    }

    public function refresh(
        PaymentTransaction $paymentTransaction,
        RefreshPaymentTransactionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Payment transaction refreshed.',
            'data' => new PaymentTransactionResource(
                $action->handle($user, $paymentTransaction)->load('order'),
            ),
        ]);
    }
}
