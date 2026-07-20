<?php

namespace App\Services\Payments\Orchestration;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Orchestration\Contracts\PaymentProviderInterface;
use App\Services\Payments\Orchestration\DTOs\PaymentInitiationRequest;
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
            $merchantReference = $this->merchantReferenceGenerator->generate();

            $transaction = PaymentTransaction::query()->create([
                'order_id' => $order->id,
                'provider' => $providerKey,
                'merchant_reference' => $merchantReference,
                'currency' => $currency,
                'amount' => $amount,
                'status' => PaymentTransactionStatus::Pending,
            ]);

            $result = $provider->initiate(new PaymentInitiationRequest(
                order: $order,
                merchantReference: $merchantReference,
                amount: $amount,
                currency: $currency,
                provider: $providerKey,
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
