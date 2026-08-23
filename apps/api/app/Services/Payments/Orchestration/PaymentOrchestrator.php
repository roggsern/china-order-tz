<?php

namespace App\Services\Payments\Orchestration;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Events\Audit\PaymentCheckoutSessionRefreshed;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Orchestration\Contracts\PaymentProviderInterface;
use App\Services\Payments\Orchestration\DTOs\PaymentInitiationRequest;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Support\Http\ApiResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment Orchestrator — provider-agnostic initiation/refresh.
 * Order is marked paid only after verified successful payment (via refresh/callback completion).
 */
class PaymentOrchestrator
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    /**
     * @param  iterable<PaymentProviderInterface>  $providers
     */
    public function __construct(
        iterable $providers,
        private readonly MerchantReferenceGenerator $merchantReferenceGenerator,
        private readonly PaymentTransactionCompletionService $completionService,
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function start(User $user, Order $order, ?string $providerKey = null, ?string $phoneNumber = null): PaymentTransaction
    {
        $this->authorizeOrder($user, $order);
        $this->assertOrderPayable($order);

        $providerKey = strtolower($providerKey ?: (string) config(
            'payments.orchestrator.default_provider',
            PaymentProvider::Nmb->value,
        ));

        $provider = $this->resolveProvider($providerKey);
        $amount = (string) ($order->grand_total ?? $order->total);
        $currency = strtoupper((string) ($order->currency ?: 'TZS'));

        $existing = $this->findLatestActiveTransaction($order->id);

        if ($existing !== null) {
            $existing = $this->refreshVerified($existing);
            $status = $this->transactionStatus($existing);

            if ($status === PaymentTransactionStatus::Successful) {
                return $existing->fresh(['order']) ?? $existing;
            }

            if ($status?->isActive()) {
                if ($this->providerKeyOf($existing) !== $providerKey) {
                    $this->throwPaymentInProgress($existing);
                }

                return $existing->fresh(['order']) ?? $existing;
            }
        }

        return DB::transaction(function () use ($order, $provider, $providerKey, $amount, $currency, $phoneNumber): PaymentTransaction {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertOrderPayable($lockedOrder);

            $existing = $this->findLatestActiveTransaction($lockedOrder->id, lock: true);

            if ($existing !== null) {
                if ($this->providerKeyOf($existing) !== $providerKey) {
                    $this->throwPaymentInProgress($existing);
                }

                return $existing->fresh(['order']) ?? $existing;
            }

            $merchantReference = $this->merchantReferenceGenerator->generate();

            $transaction = PaymentTransaction::query()->create([
                'order_id' => $lockedOrder->id,
                'provider' => $providerKey,
                'merchant_reference' => $merchantReference,
                'currency' => $currency,
                'amount' => $amount,
                'status' => PaymentTransactionStatus::Pending,
            ]);

            $result = $this->initiateWithProvider($provider, new PaymentInitiationRequest(
                order: $lockedOrder,
                merchantReference: $merchantReference,
                amount: $amount,
                currency: $currency,
                provider: $providerKey,
                paymentTransactionId: $transaction->id,
                phoneNumber: $phoneNumber,
            ));

            $transaction->fill([
                'provider_reference' => $result->providerReference,
                'external_transaction_id' => $result->externalTransactionId,
                'checkout_url' => $result->checkoutUrl,
                'success_indicator' => $result->successIndicator,
                'status' => $result->status,
                'request_payload' => $result->requestPayload,
                'response_payload' => $result->responsePayload,
                'verification_payload' => $result->verificationPayload,
                'initiated_at' => now(),
                'completed_at' => $result->status === PaymentTransactionStatus::Successful
                    ? now()
                    : null,
            ])->save();

            // Initiation alone must not mark order paid.
            return $transaction->fresh(['order']) ?? $transaction;
        });
    }

    public function show(User $user, PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->loadMissing('order');
        $this->authorizeTransaction($user, $transaction);

        return $transaction;
    }

    public function refresh(User $user, PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->loadMissing('order');
        $this->authorizeTransaction($user, $transaction);

        return $this->refreshVerified($transaction);
    }

    /**
     * Reconcile an NMB Hosted Checkout browser return without a customer session.
     *
     * Authorization is proof-based (not user ownership): the caller must present the
     * payment transaction id together with the session success indicator, merchant
     * reference, matching result indicator, and optional order id. Financial success
     * still comes only from provider verification + PaymentTransactionCompletionService.
     */
    public function reconcileNmbBrowserReturn(
        string $paymentTransactionId,
        string $merchantReference,
        string $successIndicator,
        string $resultIndicator,
        ?string $orderId = null,
    ): PaymentTransaction {
        $transaction = $this->assertNmbBrowserReturnProof(
            $paymentTransactionId,
            $merchantReference,
            $successIndicator,
            $resultIndicator,
            $orderId,
        );

        return $this->refreshVerified($transaction);
    }

    private function refreshVerified(PaymentTransaction $transaction): PaymentTransaction
    {
        if (in_array($transaction->status, [
            PaymentTransactionStatus::Successful,
            PaymentTransactionStatus::Cancelled,
        ], true)) {
            return $transaction;
        }

        $providerKey = $transaction->provider instanceof PaymentProvider
            ? $transaction->provider->value
            : (string) $transaction->provider;

        $provider = $this->resolveProvider($providerKey);

        try {
            $result = $provider->refresh($transaction);
        } catch (ValidationException $exception) {
            if ($exception->response !== null) {
                throw $exception;
            }

            ApiResponse::throwCodedValidation($exception->errors(), 'payment_failed');
        }

        return $this->completionService->applyResult($transaction, $result);
    }

    private function assertNmbBrowserReturnProof(
        string $paymentTransactionId,
        string $merchantReference,
        string $successIndicator,
        string $resultIndicator,
        ?string $orderId,
    ): PaymentTransaction {
        /** @var PaymentTransaction|null $transaction */
        $transaction = PaymentTransaction::query()
            ->with('order')
            ->whereKey($paymentTransactionId)
            ->first();

        // Uniform 404 — do not leak whether the transaction id exists.
        if ($transaction === null) {
            abort(404);
        }

        $provider = $transaction->provider instanceof PaymentProvider
            ? $transaction->provider
            : PaymentProvider::tryFrom(strtolower((string) $transaction->provider));

        if ($provider !== PaymentProvider::Nmb) {
            abort(404);
        }

        $storedSuccessIndicator = (string) ($transaction->success_indicator ?? '');
        $storedMerchantReference = (string) ($transaction->merchant_reference ?? '');

        if ($storedSuccessIndicator === '' || $storedMerchantReference === '') {
            abort(404);
        }

        if (! hash_equals($storedSuccessIndicator, $successIndicator)) {
            abort(404);
        }

        if (! hash_equals($storedMerchantReference, $merchantReference)) {
            abort(404);
        }

        // MPGS signals a completed hosted interaction when resultIndicator === successIndicator.
        if (! hash_equals($successIndicator, $resultIndicator)) {
            abort(404);
        }

        if ($orderId !== null && $orderId !== '') {
            $storedOrderId = (string) ($transaction->order_id ?? '');
            if ($storedOrderId === '' || ! hash_equals($storedOrderId, $orderId)) {
                abort(404);
            }
        }

        return $transaction;
    }

    /**
     * Create a fresh Mastercard Hosted Checkout session for an existing NMB payment.
     * Preserves payment transaction + order identity; replaces gateway session fields only.
     */
    public function retryNmbCheckoutSession(User $user, PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->loadMissing('order');
        $this->authorizeTransaction($user, $transaction);

        $lock = Cache::lock('payment-nmb-checkout-session:'.$transaction->id, 30);

        return $lock->block(15, function () use ($user, $transaction): PaymentTransaction {
            /** @var array{
             *     transaction_id: string,
             *     order: Order,
             *     merchant_reference: string,
             *     amount: string,
             *     currency: string,
             *     before: array{provider_reference: ?string, success_indicator: ?string}
             * } $context
             */
            $context = DB::transaction(function () use ($user, $transaction): array {
                /** @var PaymentTransaction $locked */
                $locked = PaymentTransaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->loadMissing('order');
                $this->authorizeTransaction($user, $locked);
                $this->assertNmbCheckoutSessionRetryable($locked);

                $order = $locked->order;
                if ($order === null) {
                    ApiResponse::throwCodedValidation([
                        'payment' => ['Payment transaction has no order.'],
                    ]);
                }

                $this->assertOrderPayable($order);

                return [
                    'transaction_id' => $locked->id,
                    'order' => $order,
                    'merchant_reference' => $locked->merchant_reference,
                    'amount' => (string) $locked->amount,
                    'currency' => strtoupper((string) $locked->currency),
                    'before' => [
                        'provider_reference' => $locked->provider_reference,
                        'success_indicator' => $locked->success_indicator,
                    ],
                ];
            });

            $provider = $this->resolveProvider(PaymentProvider::Nmb->value);
            $result = $this->initiateWithProvider($provider, new PaymentInitiationRequest(
                order: $context['order'],
                merchantReference: $context['merchant_reference'],
                amount: $context['amount'],
                currency: $context['currency'],
                provider: PaymentProvider::Nmb->value,
                paymentTransactionId: $context['transaction_id'],
                phoneNumber: null,
            ));

            if (! $result->ok || ! filled($result->providerReference)) {
                ApiResponse::throwCodedValidation([
                    'payment' => [
                        $result->message ?: 'Unable to create a fresh NMB Hosted Checkout session.',
                    ],
                ], 'payment_failed');
            }

            return DB::transaction(function () use ($user, $context, $result): PaymentTransaction {
                /** @var PaymentTransaction $locked */
                $locked = PaymentTransaction::query()
                    ->whereKey($context['transaction_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->loadMissing('order');
                $this->authorizeTransaction($user, $locked);
                $this->assertNmbCheckoutSessionRetryable($locked);

                $locked->fill([
                    'provider_reference' => $result->providerReference,
                    'checkout_url' => $result->checkoutUrl,
                    'success_indicator' => $result->successIndicator,
                    'status' => PaymentTransactionStatus::Processing,
                    'request_payload' => $result->requestPayload,
                    'response_payload' => $result->responsePayload,
                    'initiated_at' => now(),
                    'completed_at' => null,
                ])->save();

                $fresh = $locked->fresh(['order']) ?? $locked;

                event(PaymentCheckoutSessionRefreshed::fromTransaction(
                    $fresh,
                    $user,
                    $context['before'],
                    [
                        'provider_reference' => $fresh->provider_reference,
                        'success_indicator' => $fresh->success_indicator,
                    ],
                ));

                return $fresh;
            });
        });
    }

    public function resolveProvider(string $key): PaymentProviderInterface
    {
        $key = strtolower($key);

        if (! isset($this->providers[$key])) {
            ApiResponse::throwCodedValidation([
                'provider' => ["Payment provider [{$key}] is not registered."],
            ], 'payment_failed');
        }

        return $this->providers[$key];
    }

    /**
     * @return list<string>
     */
    public function registeredProviders(): array
    {
        return array_keys($this->providers);
    }

    private function findLatestActiveTransaction(string $orderId, bool $lock = false): ?PaymentTransaction
    {
        $query = PaymentTransaction::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [
                PaymentTransactionStatus::Pending,
                PaymentTransactionStatus::Processing,
            ])
            ->orderByDesc('created_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function providerKeyOf(PaymentTransaction $transaction): string
    {
        return $transaction->provider instanceof PaymentProvider
            ? $transaction->provider->value
            : strtolower((string) $transaction->provider);
    }

    private function transactionStatus(PaymentTransaction $transaction): ?PaymentTransactionStatus
    {
        return $transaction->status instanceof PaymentTransactionStatus
            ? $transaction->status
            : PaymentTransactionStatus::tryFrom((string) $transaction->status);
    }

    private function throwPaymentInProgress(PaymentTransaction $transaction): never
    {
        $status = $this->transactionStatus($transaction);

        ApiResponse::throwCodedValidation(
            ['provider' => ['An active payment is already in progress for this order.']],
            'payment_in_progress',
            extra: [
                'payment_transaction_id' => $transaction->id,
                'payment_transaction_status' => $status?->value,
                'provider' => $this->providerKeyOf($transaction),
            ],
        );
    }

    private function authorizeOrder(User $user, Order $order): void
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }
    }

    private function authorizeTransaction(User $user, PaymentTransaction $transaction): void
    {
        if ($transaction->order?->user_id !== $user->id) {
            abort(404);
        }
    }

    private function assertNmbCheckoutSessionRetryable(PaymentTransaction $transaction): void
    {
        $provider = $transaction->provider instanceof PaymentProvider
            ? $transaction->provider
            : PaymentProvider::tryFrom(strtolower((string) $transaction->provider));

        if ($provider !== PaymentProvider::Nmb) {
            ApiResponse::throwCodedValidation([
                'provider' => ['Only NMB payment transactions can refresh a Hosted Checkout session.'],
            ]);
        }

        $status = $transaction->status instanceof PaymentTransactionStatus
            ? $transaction->status
            : PaymentTransactionStatus::tryFrom((string) $transaction->status);

        $retryable = in_array($status, [
            PaymentTransactionStatus::Pending,
            PaymentTransactionStatus::Processing,
            PaymentTransactionStatus::Failed,
        ], true);

        if (! $retryable) {
            ApiResponse::throwCodedValidation([
                'payment' => ['This payment can no longer refresh its Hosted Checkout session.'],
            ]);
        }
    }

    private function assertOrderPayable(Order $order): void
    {
        $status = $order->status;

        $payable = in_array($status, [
            OrderStatus::PendingPayment,
            OrderStatus::Pending,
        ], true);

        if (! $payable) {
            ApiResponse::throwCodedValidation([
                'order' => ['Only pending payment orders can start a payment transaction.'],
            ]);
        }

        $order->loadMissing(['deliveryOption', 'checkoutSession']);

        $option = $order->deliveryOption;
        if ($option === null || $option->delivery_type === null) {
            ApiResponse::throwCodedValidation([
                'shipping_choice' => ['Select a shipping option before payment.'],
            ]);
        }

        $choice = $option->delivery_type instanceof DeliveryType
            ? $option->delivery_type
            : DeliveryType::from((string) $option->delivery_type);

        $shippingAmount = (string) ($order->shipping_amount ?? '0.00');
        $grandTotal = (string) ($order->grand_total ?? $order->total);

        if ($choice === DeliveryType::CompanyShipping) {
            if ($option->shipping_method === null) {
                ApiResponse::throwCodedValidation([
                    'shipping_method' => ['Company shipping requires air or sea before payment.'],
                ]);
            }
            if (bccomp($shippingAmount, '0.00', 2) <= 0) {
                ApiResponse::throwCodedValidation([
                    'shipping' => ['Company shipping total must be included before payment.'],
                ]);
            }
        }

        if (in_array($choice, [
            DeliveryType::CustomerAgent,
            DeliveryType::SelfPickup,
            DeliveryType::NegotiatedDelivery,
        ], true) && bccomp($shippingAmount, '0.00', 2) !== 0) {
            ApiResponse::throwCodedValidation([
                'shipping' => ['This shipping choice must have zero company shipping charges.'],
            ]);
        }

        if (bccomp($grandTotal, '0.00', 2) <= 0) {
            ApiResponse::throwCodedValidation([
                'order' => ['Order amount must be greater than zero.'],
            ]);
        }
    }

    /**
     * Provider initiate with Contract v1 payment_failed on gateway ValidationException.
     * Does not alter request/response payloads sent to the gateway.
     */
    private function initiateWithProvider(
        PaymentProviderInterface $provider,
        PaymentInitiationRequest $request,
    ): PaymentProviderResult {
        try {
            return $provider->initiate($request);
        } catch (ValidationException $exception) {
            if ($exception->response !== null) {
                throw $exception;
            }

            ApiResponse::throwCodedValidation($exception->errors(), 'payment_failed');
        }
    }
}
