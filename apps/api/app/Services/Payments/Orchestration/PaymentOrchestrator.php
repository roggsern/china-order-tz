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

    public function start(User $user, Order $order, ?string $providerKey = null): PaymentTransaction
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

        return DB::transaction(function () use ($order, $provider, $providerKey, $amount, $currency): PaymentTransaction {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertOrderPayable($lockedOrder);

            $existing = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', [
                    PaymentTransactionStatus::Pending,
                    PaymentTransactionStatus::Processing,
                ])
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existingProvider = $existing->provider instanceof PaymentProvider
                    ? $existing->provider->value
                    : strtolower((string) $existing->provider);

                if ($existingProvider !== $providerKey) {
                    throw ValidationException::withMessages([
                        'provider' => ['An active payment is already in progress for this order.'],
                    ]);
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

            $result = $provider->initiate(new PaymentInitiationRequest(
                order: $lockedOrder,
                merchantReference: $merchantReference,
                amount: $amount,
                currency: $currency,
                provider: $providerKey,
                paymentTransactionId: $transaction->id,
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
        $result = $provider->refresh($transaction);

        return $this->completionService->applyResult($transaction, $result);
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
                    throw ValidationException::withMessages([
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
            $result = $provider->initiate(new PaymentInitiationRequest(
                order: $context['order'],
                merchantReference: $context['merchant_reference'],
                amount: $context['amount'],
                currency: $context['currency'],
                provider: PaymentProvider::Nmb->value,
                paymentTransactionId: $context['transaction_id'],
            ));

            if (! $result->ok || ! filled($result->providerReference)) {
                throw ValidationException::withMessages([
                    'payment' => [
                        $result->message ?: 'Unable to create a fresh NMB Hosted Checkout session.',
                    ],
                ]);
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
            throw ValidationException::withMessages([
                'provider' => ["Payment provider [{$key}] is not registered."],
            ]);
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
            throw ValidationException::withMessages([
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
            throw ValidationException::withMessages([
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
            throw ValidationException::withMessages([
                'order' => ['Only pending payment orders can start a payment transaction.'],
            ]);
        }

        $order->loadMissing(['deliveryOption', 'checkoutSession']);

        $option = $order->deliveryOption;
        if ($option === null || $option->delivery_type === null) {
            throw ValidationException::withMessages([
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
                throw ValidationException::withMessages([
                    'shipping_method' => ['Company shipping requires air or sea before payment.'],
                ]);
            }
            if (bccomp($shippingAmount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'shipping' => ['Company shipping total must be included before payment.'],
                ]);
            }
        }

        if (in_array($choice, [
            DeliveryType::CustomerAgent,
            DeliveryType::SelfPickup,
            DeliveryType::NegotiatedDelivery,
        ], true) && bccomp($shippingAmount, '0.00', 2) !== 0) {
            throw ValidationException::withMessages([
                'shipping' => ['This shipping choice must have zero company shipping charges.'],
            ]);
        }

        if (bccomp($grandTotal, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'order' => ['Order amount must be greater than zero.'],
            ]);
        }
    }
}
