<?php

namespace Tests\Support;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\User;

trait InteractsWithCheckoutShipping
{
    /**
     * @param  array{
     *     shipping_choice?: string,
     *     shipping_method?: string|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null
     * }  $overrides
     */
    protected function applyCheckoutShippingChoice(string $sessionId, array $overrides = []): void
    {
        $payload = array_merge([
            'shipping_choice' => 'customer_agent',
        ], $overrides);

        $this->postJson("/api/v1/checkout/{$sessionId}/shipping-choice", $payload)
            ->assertOk();
    }

    /**
     * Start checkout, apply explicit shipping choice, create order.
     *
     * @param  array{
     *     shipping_choice?: string,
     *     shipping_method?: string|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null
     * }  $shipping
     * @return array{session_id: string, order_id: string}
     */
    protected function createOrderWithShippingChoice(array $shipping = []): array
    {
        $sessionId = $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->json('data.id');

        $this->applyCheckoutShippingChoice($sessionId, $shipping);

        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        return ['session_id' => $sessionId, 'order_id' => $orderId];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createPayableOrder(User $user, array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
            'total' => 45000,
            'shipping_amount' => 0,
            'currency' => 'TZS',
        ], $attributes));

        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CustomerAgent,
            'shipping_method' => null,
            'agent_name' => null,
            'agent_contact' => null,
        ]);

        return $order->fresh(['deliveryOption']) ?? $order;
    }
}
