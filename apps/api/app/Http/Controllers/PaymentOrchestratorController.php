<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ReconcileNmbBrowserReturnAction;
use App\Actions\Payments\RefreshPaymentTransactionAction;
use App\Actions\Payments\ResolvePaymentReturnTransactionAction;
use App\Actions\Payments\RetryNmbCheckoutSessionAction;
use App\Actions\Payments\ShowPaymentTransactionAction;
use App\Actions\Payments\StartPaymentTransactionAction;
use App\Http\Requests\Payments\ReconcileNmbBrowserReturnRequest;
use App\Http\Requests\Payments\ResolvePaymentReturnRequest;
use App\Http\Requests\Payments\StartPaymentTransactionRequest;
use App\Http\Resources\PaymentTransactionResource;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Http\ApiResponse;
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
            $request->validated('phone_number'),
        );

        return ApiResponse::success(
            data: new PaymentTransactionResource($transaction->load('order')),
            message: 'Payment transaction started.',
            status: 201,
        );
    }

    public function show(
        PaymentTransaction $paymentTransaction,
        ShowPaymentTransactionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new PaymentTransactionResource(
                $action->handle($user, $paymentTransaction)->load('order'),
            ),
        );
    }

    public function refresh(
        PaymentTransaction $paymentTransaction,
        RefreshPaymentTransactionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            data: new PaymentTransactionResource(
                $action->handle($user, $paymentTransaction)->load('order'),
            ),
            message: 'Payment transaction refreshed.',
        );
    }

    public function retryNmbCheckoutSession(
        PaymentTransaction $paymentTransaction,
        RetryNmbCheckoutSessionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $transaction = $action->handle($user, $paymentTransaction);

        return ApiResponse::success(
            data: new PaymentTransactionResource($transaction->load('order')),
            message: 'NMB Hosted Checkout session refreshed.',
        );
    }

    public function resolveReturn(
        ResolvePaymentReturnRequest $request,
        ResolvePaymentReturnTransactionAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $transaction = $action->handle(
            $user,
            $request->validated('order_id'),
            $request->validated('merchant_reference'),
        );

        return ApiResponse::success(
            data: new PaymentTransactionResource($transaction),
            message: 'Payment return transaction resolved.',
        );
    }

    /**
     * Unauthenticated NMB Hosted Checkout return reconciliation.
     * Proof-based; does not replace authenticated customer refresh.
     * Response envelope only — reconciliation / gateway verification unchanged.
     */
    public function reconcileNmbBrowserReturn(
        ReconcileNmbBrowserReturnRequest $request,
        ReconcileNmbBrowserReturnAction $action,
    ): JsonResponse {
        $transaction = $action->handle(
            (string) $request->validated('payment_transaction_id'),
            (string) $request->validated('merchant_reference'),
            (string) $request->validated('success_indicator'),
            (string) $request->validated('result_indicator'),
            $request->validated('order_id'),
        );

        return ApiResponse::success(
            data: new PaymentTransactionResource($transaction->load('order')),
            message: 'Payment return reconciled.',
        );
    }
}
