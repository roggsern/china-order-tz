<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryMarket;
use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\CheckoutSession;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\User;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Delivery Option Engine — records how a paid order leaves our responsibility.
 * Does not create shipments, calculate shipping prices, or assign couriers.
 */
class DeliveryOptionEngine
{
    public function __construct(
        private readonly DeliveryTypeResolver $typeResolver,
        private readonly DeliveryOptionValidator $validator,
        private readonly ProductShippingOptionEngine $shippingOptionEngine,
    ) {}

    /**
     * @return array{
     *     market: string,
     *     market_label: string,
     *     delivery_types: list<array{value: string, label: string}>,
     *     shipping_methods: list<array{value: string, label: string, price?: string|null, currency?: string}>
     * }
     */
    public function availableOptions(Order $order): array
    {
        $market = $this->typeResolver->resolveMarket($order);

        return [
            'market' => $market->value,
            'market_label' => $market->label(),
            'delivery_types' => array_map(
                fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                $this->typeResolver->allowedTypes($order),
            ),
            'shipping_methods' => $market === DeliveryMarket::China
                ? array_map(
                    fn (array $method) => [
                        'value' => $method['value'],
                        'label' => $method['label'],
                    ],
                    $this->shippingOptionEngine->availableMethodsForOrder($order),
                )
                : [],
        ];
    }

    /**
     * Persist the pre-payment shipping choice onto the order at create time.
     * Agent contact details may still be completed later via update().
     */
    public function createFromCheckoutSession(Order $order, CheckoutSession $session): DeliveryOption
    {
        $type = DeliveryType::tryFrom((string) $session->shipping_choice);
        if ($type === null) {
            throw ValidationException::withMessages([
                'shipping_choice' => ['Checkout session is missing a valid shipping choice.'],
            ]);
        }

        $method = null;
        if ($type === DeliveryType::CompanyShipping) {
            $method = DeliveryShippingMethod::tryFrom((string) ($session->shipping_method ?? ''));
            if ($method === null) {
                throw ValidationException::withMessages([
                    'shipping_method' => ['Company shipping requires air or sea.'],
                ]);
            }
        }

        return DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => $type,
            'shipping_method' => $method,
            'delivery_status' => DeliveryOptionStatus::Pending,
            'agent_name' => $session->agent_name,
            'agent_contact' => $session->agent_contact,
            'notes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function select(User $user, Order $order, array $input): DeliveryOption
    {
        $this->authorize($user, $order);
        $this->assertOrderEligibleForSelect($order);
        $order->loadMissing('deliveryOption');

        if ($order->deliveryOption !== null) {
            throw ValidationException::withMessages([
                'order' => ['Delivery option already selected. Use PATCH to update agent details.'],
            ]);
        }

        $validated = $this->validator->validateForOrder($order, $input, requireAgentDetails: true);

        return DB::transaction(function () use ($order, $validated): DeliveryOption {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (DeliveryOption::query()->where('order_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'order' => ['Delivery option already selected. Use PATCH to update agent details.'],
                ]);
            }

            $option = DeliveryOption::query()->create([
                'order_id' => $locked->id,
                'delivery_type' => $validated['delivery_type'],
                'shipping_method' => $validated['shipping_method'],
                'delivery_status' => DeliveryOptionStatus::Pending,
                'agent_name' => $validated['agent_name'],
                'agent_contact' => $validated['agent_contact'],
                'agent_company' => $validated['agent_company'] ?? null,
                'agent_phone' => $validated['agent_phone'] ?? null,
                'agent_email' => $validated['agent_email'] ?? null,
                'notes' => $validated['notes'],
            ]);

            return $option->fresh(['order']) ?? $option;
        });
    }

    public function show(User $user, Order $order): ?DeliveryOption
    {
        $this->authorize($user, $order);
        $order->loadMissing('deliveryOption');

        return $order->deliveryOption;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, Order $order, array $input): DeliveryOption
    {
        $this->authorize($user, $order);
        $this->assertOrderEligibleForUpdate($order);
        $order->loadMissing('deliveryOption');

        $option = $order->deliveryOption;
        if ($option === null) {
            throw ValidationException::withMessages([
                'order' => ['No delivery option exists. Use POST to select one first.'],
            ]);
        }

        $payload = [
            'delivery_type' => array_key_exists('delivery_type', $input)
                ? $input['delivery_type']
                : ($option->delivery_type?->value ?? (string) $option->delivery_type),
            'shipping_method' => array_key_exists('shipping_method', $input)
                ? $input['shipping_method']
                : ($option->shipping_method?->value),
            'agent_name' => array_key_exists('agent_name', $input)
                ? $input['agent_name']
                : $option->agent_name,
            'agent_contact' => array_key_exists('agent_contact', $input)
                ? $input['agent_contact']
                : $option->agent_contact,
            'notes' => array_key_exists('notes', $input)
                ? $input['notes']
                : $option->notes,
        ];

        if (array_key_exists('delivery_status', $input)) {
            $payload['delivery_status'] = $input['delivery_status'];
        }

        $lockedType = $option->delivery_type instanceof DeliveryType
            ? $option->delivery_type
            : DeliveryType::from((string) $option->delivery_type);

        if (array_key_exists('delivery_type', $input)) {
            $incomingType = DeliveryType::tryFrom((string) $input['delivery_type']);
            if ($incomingType !== null && $incomingType !== $lockedType) {
                throw ValidationException::withMessages([
                    'delivery_type' => ['Shipping choice was locked at checkout and cannot change the payable amount.'],
                ]);
            }
        }

        if (array_key_exists('shipping_method', $input)) {
            $lockedMethod = $option->shipping_method instanceof DeliveryShippingMethod
                ? $option->shipping_method->value
                : $option->shipping_method;
            if ((string) ($input['shipping_method'] ?? '') !== (string) ($lockedMethod ?? '')) {
                throw ValidationException::withMessages([
                    'shipping_method' => ['Shipping method was locked at checkout and cannot be changed after payment.'],
                ]);
            }
        }

        // Agent details may be completed after payment; do not re-require empty fields on status-only updates.
        $requireAgent = $lockedType === DeliveryType::CustomerAgent
            && (array_key_exists('agent_name', $input) || array_key_exists('agent_contact', $input));

        $validated = $this->validator->validateForOrder(
            $order,
            $payload,
            requireAgentDetails: $requireAgent,
        );

        return DB::transaction(function () use ($option, $validated): DeliveryOption {
            /** @var DeliveryOption $locked */
            $locked = DeliveryOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            $currentStatus = $locked->delivery_status instanceof DeliveryOptionStatus
                ? $locked->delivery_status
                : DeliveryOptionStatus::from((string) $locked->delivery_status);

            if ($currentStatus === DeliveryOptionStatus::Completed) {
                throw ValidationException::withMessages([
                    'delivery_status' => ['Completed delivery options cannot be changed.'],
                ]);
            }

            $nextStatus = $validated['delivery_status'];

            if ($nextStatus !== null && $nextStatus !== $currentStatus) {
                if (! $currentStatus->canTransitionTo($nextStatus)) {
                    throw ValidationException::withMessages([
                        'delivery_status' => [
                            "Cannot transition delivery status from [{$currentStatus->value}] to [{$nextStatus->value}].",
                        ],
                    ]);
                }

                $locked->delivery_status = $nextStatus;

                if ($nextStatus === DeliveryOptionStatus::Confirmed && $locked->confirmed_at === null) {
                    $locked->confirmed_at = now();
                }
            }

            $locked->fill([
                'delivery_type' => $validated['delivery_type'],
                'shipping_method' => $validated['shipping_method'],
                'agent_name' => $validated['agent_name'],
                'agent_contact' => $validated['agent_contact'],
                'agent_company' => $validated['agent_company'] ?? $locked->agent_company,
                'agent_phone' => $validated['agent_phone'] ?? $locked->agent_phone,
                'agent_email' => $validated['agent_email'] ?? $locked->agent_email,
                'notes' => $validated['notes'] ?? $locked->notes,
            ])->save();

            return $locked->fresh(['order']) ?? $locked;
        });
    }

    private function authorize(User $user, Order $order): void
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }
    }

    private function assertOrderEligibleForSelect(Order $order): void
    {
        $allowed = [
            OrderStatus::Paid,
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
        ];

        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'order' => ['Delivery options can only be set after the order is paid.'],
            ]);
        }
    }

    private function assertOrderEligibleForUpdate(Order $order): void
    {
        $allowed = [
            OrderStatus::PendingPayment,
            OrderStatus::Pending,
            OrderStatus::Paid,
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
        ];

        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'order' => ['Delivery options can only be managed on open or paid orders.'],
            ]);
        }
    }
}
