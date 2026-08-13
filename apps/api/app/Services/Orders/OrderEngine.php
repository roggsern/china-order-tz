<?php

namespace App\Services\Orders;

use App\Enums\CheckoutSessionStatus;
use App\Enums\DeliveryType;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Events\Audit\OrderCreated as OrderCreatedAudit;
use App\Events\Commerce\CommerceOrderCreated;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutOrchestrator;
use App\Services\Checkout\CheckoutShippingChoiceService;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\CostProfit\CostEngine;
use App\Services\Delivery\DeliveryOptionEngine;
use App\Services\Inventory\ReservationService;
use App\Services\Inventory\DTOs\ReservationContext;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Profile\CustomerAddressService;
use App\Services\Promotions\PromotionUsageService;
use App\Support\Http\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order Engine — permanent business records from validated Checkout Sessions.
 * Snapshots commercial data via OrderSnapshotEngine. Does not charge, reserve, or ship.
 *
 * RC1-C1 — Exactly-once creation under concurrent from-checkout requests.
 */
class OrderEngine
{
    public function __construct(
        private readonly CheckoutOrchestrator $checkoutOrchestrator,
        private readonly CheckoutShippingChoiceService $shippingChoice,
        private readonly DeliveryOptionEngine $deliveryOptionEngine,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly CartService $cartService,
        private readonly OrderSnapshotEngine $snapshotEngine,
        private readonly NotificationPlatform $notifications,
        private readonly CommerceChannelResolver $commerceChannelResolver,
        private readonly CostEngine $costEngine,
        private readonly PromotionUsageService $promotionUsages,
        private readonly OrderLifecycleEngine $lifecycle,
        private readonly ReservationService $reservationService,
        private readonly CustomerAddressService $customerAddresses,
        private readonly OrderShippingAddressSnapshotService $shippingAddressSnapshot,
    ) {}

    public function createFromCheckoutSession(User $user, CheckoutSession $session): Order
    {
        $this->assertOwned($user, $session);

        /**
         * Side effects after commit only.
         * Sync WhatsApp/email/push inside the request+DB transaction previously held the
         * HTTP response open long enough for mobile's 30s abort → nginx 499 while FPM still
         * finished 201 (order created, payment never started).
         *
         * @var array{
         *     order: Order,
         *     channel: CommerceChannel,
         *     channel_snapshot: array<string, mixed>
         * }|null
         */
        $createdSideEffects = null;

        try {
            $order = DB::transaction(function () use ($user, $session, &$createdSideEffects): Order {
                /** @var CheckoutSession $locked */
                $locked = CheckoutSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertOwned($user, $locked);

                $locked = $this->markExpiredIfNeeded($locked);

                if ($locked->isExpired() || $locked->status === CheckoutSessionStatus::Expired) {
                    ApiResponse::throwCodedValidation([
                        'session' => ['Checkout session has expired.'],
                    ]);
                }

                if ($locked->status === CheckoutSessionStatus::Completed) {
                    return $this->existingOrderForSession($locked);
                }

                if ($locked->status !== CheckoutSessionStatus::Validated) {
                    ApiResponse::throwCodedValidation([
                        'session' => ['Checkout session must be validated before creating an order.'],
                    ]);
                }

                $this->assertDeliveryAddress($user);

                // Idempotent if a prior attempt created the order but failed before Completed.
                $preexisting = Order::query()
                    ->where('checkout_session_id', $locked->id)
                    ->lockForUpdate()
                    ->first();
                if ($preexisting !== null) {
                    $this->checkoutOrchestrator->markCompleted($locked);
                    $this->shippingAddressSnapshot->ensureFromDeliveryAddress($preexisting, $user);

                    return $this->loadOrderPayload($preexisting);
                }

                $locked = $this->checkoutOrchestrator->loadSession($locked);
                $this->shippingChoice->assertReadyForOrder($locked);

                // Always re-price via CommercePricingResolver (validateCart → ResolveCartPurchasable).
                $totals = $this->checkoutOrchestrator->validateCart(
                    $locked->cart,
                    $user,
                    $locked->applied_promotion_code,
                    $locked->promotion_id,
                );

                $choice = DeliveryType::from((string) $locked->shipping_choice);
                if (in_array($choice, [
                    DeliveryType::CustomerAgent,
                    DeliveryType::SelfPickup,
                    DeliveryType::NegotiatedDelivery,
                ], true)) {
                    $totals['shipping_total'] = '0.00';
                    $totals['grand_total'] = bcsub(
                        bcadd(bcadd($totals['subtotal'], '0.00', 2), $totals['tax_total'] ?? '0.00', 2),
                        $totals['discount_total'] ?? '0.00',
                        2,
                    );
                }

                $this->assertTotalsMatchSession($locked, $totals);

                $fingerprint = $this->shippingChoice->fingerprint(
                    $this->cartService->loadCart($locked->cart()->firstOrFail()),
                    $choice,
                    $locked->shipping_method,
                );
                if ($locked->cart_fingerprint !== null && ! hash_equals((string) $locked->cart_fingerprint, $fingerprint)) {
                    ApiResponse::throwCodedValidation([
                        'session' => ['Checkout totals are stale. Refresh checkout and confirm shipping again.'],
                    ]);
                }

                $locked->applyTotals($totals);
                $locked->save();
                $discountResolution = $totals['resolution'] ?? null;

                $cart = $this->cartService->loadCart($locked->cart()->firstOrFail());

                if ($cart->isEmpty()) {
                    ApiResponse::throwCodedValidation([
                        'cart' => ['Cannot create an order from an empty cart.'],
                    ]);
                }

                $channel = $this->commerceChannelResolver->assertCartSingleChannel($cart);
                $channelSnapshot = $this->commerceChannelResolver->snapshot($channel);

                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'storefront_visitor_id' => $locked->storefront_visitor_id,
                    'storefront_session_id' => $locked->storefront_session_id,
                    'commerce_channel_id' => $channel->id,
                    'commerce_channel_snapshot' => $channelSnapshot,
                    'checkout_session_id' => $locked->id,
                    'order_number' => $this->orderNumberGenerator->generate(),
                    'status' => OrderStatus::PendingPayment,
                    'subtotal' => $locked->subtotal,
                    'discount_amount' => $locked->discount_total,
                    'tax_amount' => $locked->tax_total,
                    'shipping_amount' => $locked->shipping_total,
                    'total' => $locked->grand_total,
                    'currency' => $locked->currency ?: 'TZS',
                    'is_demo' => $cart->items->every(fn (CartItem $item) => (bool) $item->product?->is_demo),
                    'placed_at' => now(),
                ]);

                $this->shippingAddressSnapshot->ensureFromDeliveryAddress($order, $user);

                foreach ($cart->items as $item) {
                    $order->items()->create(
                        $this->snapshotEngine->snapshotFromCartItem($item, $locked->currency ?: 'TZS')
                    );
                }

                $this->deliveryOptionEngine->createFromCheckoutSession($order, $locked);

                try {
                    $this->lifecycle->recordCreated(
                        $order,
                        OrderLifecycleContext::system('order_engine', 'Order created from checkout session'),
                    );
                } catch (\Throwable $e) {
                    Log::warning('lifecycle.record_created_failed', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }

                $order = $order->load(['items.variant', 'items.product', 'deliveryOption']);
                try {
                    $this->costEngine->captureForOrder($order);
                } catch (\Throwable $e) {
                    Log::warning('cost.capture_on_order_create_failed', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }

                if ($discountResolution !== null) {
                    try {
                        $this->promotionUsages->recordForOrder($order, $discountResolution, $user);
                    } catch (\Throwable $e) {
                        Log::warning('promotion.record_usage_failed', [
                            'order_id' => $order->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                $this->checkoutOrchestrator->markCompleted($locked);
                $this->cartService->finalizeAfterOrder($user);

                $order = $this->loadOrderPayload($order);

                $createdSideEffects = [
                    'order' => $order,
                    'channel' => $channel,
                    'channel_snapshot' => $channelSnapshot,
                ];

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = Order::query()
                ->where('checkout_session_id', $session->id)
                ->first();

            if ($existing !== null && $existing->user_id === $user->id) {
                $this->shippingAddressSnapshot->ensureFromDeliveryAddress($existing, $user);

                return $this->loadOrderPayload($existing);
            }

            ApiResponse::throwCodedValidation([
                'session' => ['Checkout session is already completed.'],
            ]);
        }

        if ($createdSideEffects !== null) {
            // Return 201 to the client first; sync channel HTTP (WhatsApp/email/push)
            // must not compete with the mobile API timeout window.
            $userId = $user->id;
            $orderId = $createdSideEffects['order']->id;
            $channelId = $createdSideEffects['channel']->id;
            $channelSnapshot = $createdSideEffects['channel_snapshot'];

            dispatch(function () use ($userId, $orderId, $channelId, $channelSnapshot): void {
                $user = User::query()->find($userId);
                $order = Order::query()->find($orderId);
                $channel = CommerceChannel::query()->find($channelId);
                if ($user === null || $order === null || $channel === null) {
                    return;
                }

                app(self::class)->dispatchOrderCreatedSideEffects(
                    $user,
                    $order,
                    $channel,
                    $channelSnapshot,
                );
            })->afterResponse();
        }

        return $order;
    }

    /**
     * Post-response order_created fan-out — must not block the from-checkout HTTP response.
     *
     * @param  array<string, mixed>  $channelSnapshot
     */
    public function dispatchOrderCreatedSideEffects(
        User $user,
        Order $order,
        CommerceChannel $channel,
        array $channelSnapshot,
    ): void {
        try {
            $notifyKey = 'order_created:'.$order->id.':'.$user->id;
            $this->notifications->notifyCustomer(
                NotificationEventType::OrderCreated,
                $user,
                [
                    'customer_name' => $user->name,
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'order_total' => (string) $order->total,
                    'currency' => $order->currency,
                    'commerce_channel' => $channelSnapshot['code'] ?? null,
                    'commerce_channel_label' => $channelSnapshot['customer_label'] ?? null,
                ],
                idempotencyKey: $notifyKey,
                correlationKey: $notifyKey,
            );
        } catch (\Throwable $e) {
            Log::warning('notification.order_created_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            event(OrderCreatedAudit::fromOrder($order, $user));
        } catch (\Throwable $e) {
            Log::warning('audit.order_created_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            event(new CommerceOrderCreated($order, $channel, $channelSnapshot));
        } catch (\Throwable $e) {
            Log::warning('commerce.order_created_event_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function existingOrderForSession(CheckoutSession $session): Order
    {
        $existing = Order::query()
            ->where('checkout_session_id', $session->id)
            ->first();

        if ($existing === null) {
            ApiResponse::throwCodedValidation([
                'session' => ['Checkout session is already completed.'],
            ]);
        }

        $user = $existing->user;
        if ($user !== null) {
            $this->shippingAddressSnapshot->ensureFromDeliveryAddress($existing, $user);
        }

        return $this->loadOrderPayload($existing);
    }

    private function loadOrderPayload(Order $order): Order
    {
        return $order->load([
            'items.variant',
            'items.product',
            'items.costSnapshot',
            'checkoutSession',
            'user',
            'commerceChannel',
            'deliveryOption',
            'shippingAddress',
        ]);
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    private function assertTotalsMatchSession(CheckoutSession $session, array $totals): void
    {
        $checks = [
            'subtotal' => (string) $session->subtotal,
            'shipping_total' => (string) $session->shipping_total,
            'discount_total' => (string) ($session->discount_total ?? '0.00'),
            'grand_total' => (string) $session->grand_total,
        ];

        foreach ($checks as $key => $expected) {
            $actual = (string) ($totals[$key] ?? '0.00');
            if (bccomp($actual, $expected, 2) !== 0) {
                ApiResponse::throwCodedValidation([
                    'session' => ['Checkout totals are stale. Refresh checkout and confirm shipping again.'],
                ]);
            }
        }
    }

    private function assertOwned(User $user, CheckoutSession $session): void
    {
        if ($session->user_id !== $user->id) {
            abort(404);
        }
    }

    private function assertDeliveryAddress(User $user): void
    {
        $user->unsetRelation('deliveryAddress');
        $user->load('deliveryAddress');

        if ($user->deliveryAddress === null) {
            $this->customerAddresses->ensureDeliveryAddressFromDefault($user);
            $user->unsetRelation('deliveryAddress');
            $user->load('deliveryAddress');
        }

        if ($user->deliveryAddress === null) {
            ApiResponse::throwCodedValidation([
                'delivery_address' => ['Delivery address is required before checkout.'],
            ]);
        }
    }

    private function markExpiredIfNeeded(CheckoutSession $session): CheckoutSession
    {
        if (
            $session->status !== CheckoutSessionStatus::Expired
            && $session->status !== CheckoutSessionStatus::Completed
            && $session->expires_at !== null
            && $session->expires_at->isPast()
        ) {
            $this->reservationService->expire(new ReservationContext(
                checkoutSession: $session,
                cart: $session->cart,
                source: 'checkout_timeout',
            ));

            $session->status = CheckoutSessionStatus::Expired;
            $session->save();
        }

        return $session->fresh() ?? $session;
    }
}
