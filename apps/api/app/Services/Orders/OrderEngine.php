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
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutOrchestrator;
use App\Services\Checkout\CheckoutShippingChoiceService;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\CostProfit\CostEngine;
use App\Services\Delivery\DeliveryOptionEngine;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Promotions\PromotionUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Order Engine — permanent business records from validated Checkout Sessions.
 * Snapshots commercial data via OrderSnapshotEngine. Does not charge, reserve, or ship.
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
    ) {}

    public function createFromCheckoutSession(User $user, CheckoutSession $session): Order
    {
        $this->assertOwned($user, $session);

        $session = $this->checkoutOrchestrator->loadSession(
            $this->markExpiredIfNeeded($session),
        );

        if ($session->isExpired() || $session->status === CheckoutSessionStatus::Expired) {
            throw ValidationException::withMessages([
                'session' => ['Checkout session has expired.'],
            ]);
        }

        if ($session->status === CheckoutSessionStatus::Completed) {
            throw ValidationException::withMessages([
                'session' => ['Checkout session is already completed.'],
            ]);
        }

        if ($session->status !== CheckoutSessionStatus::Validated) {
            throw ValidationException::withMessages([
                'session' => ['Checkout session must be validated before creating an order.'],
            ]);
        }

        $this->shippingChoice->assertReadyForOrder($session);

        // Re-validate cart/pricing/inventory + applied promotion through the orchestrator.
        $totals = $this->checkoutOrchestrator->validateCart(
            $session->cart,
            $user,
            $session->applied_promotion_code,
            $session->promotion_id,
        );

        $choice = DeliveryType::from((string) $session->shipping_choice);
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

        $this->assertTotalsMatchSession($session, $totals);

        $fingerprint = $this->shippingChoice->fingerprint(
            $this->cartService->loadCart($session->cart()->firstOrFail()),
            $choice,
            $session->shipping_method,
        );
        if ($session->cart_fingerprint !== null && ! hash_equals((string) $session->cart_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages([
                'session' => ['Checkout totals are stale. Refresh checkout and confirm shipping again.'],
            ]);
        }

        $session->applyTotals($totals);
        $session->save();
        $discountResolution = $totals['resolution'] ?? null;

        return DB::transaction(function () use ($user, $session, $discountResolution): Order {
            $cart = $this->cartService->loadCart($session->cart()->firstOrFail());

            if ($cart->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Cannot create an order from an empty cart.'],
                ]);
            }

            $channel = $this->commerceChannelResolver->assertCartSingleChannel($cart);
            $channelSnapshot = $this->commerceChannelResolver->snapshot($channel);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'commerce_channel_id' => $channel->id,
                'commerce_channel_snapshot' => $channelSnapshot,
                'checkout_session_id' => $session->id,
                'order_number' => $this->orderNumberGenerator->generate(),
                'status' => OrderStatus::PendingPayment,
                'subtotal' => $session->subtotal,
                'discount_amount' => $session->discount_total,
                'tax_amount' => $session->tax_total,
                'shipping_amount' => $session->shipping_total,
                'total' => $session->grand_total,
                'currency' => $session->currency ?: 'TZS',
                'is_demo' => $cart->items->every(fn (CartItem $item) => (bool) $item->product?->is_demo),
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create(
                    $this->snapshotEngine->snapshotFromCartItem($item, $session->currency ?: 'TZS')
                );
            }

            $this->deliveryOptionEngine->createFromCheckoutSession($order, $session);

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

            $this->checkoutOrchestrator->markCompleted($session);
            $this->cartService->finalizeAfterOrder($user);

            $order = $order->load([
                'items.variant',
                'items.product',
                'items.costSnapshot',
                'checkoutSession',
                'user',
                'commerceChannel',
                'deliveryOption',
            ]);

            try {
                $this->notifications->notifyCustomer(NotificationEventType::OrderCreated, $user, [
                    'customer_name' => $user->name,
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'order_total' => (string) $order->total,
                    'currency' => $order->currency,
                    'commerce_channel' => $channelSnapshot['code'] ?? null,
                    'commerce_channel_label' => $channelSnapshot['customer_label'] ?? null,
                ]);
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

            return $order;
        });
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
                throw ValidationException::withMessages([
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

    private function markExpiredIfNeeded(CheckoutSession $session): CheckoutSession
    {
        if (
            $session->status !== CheckoutSessionStatus::Expired
            && $session->status !== CheckoutSessionStatus::Completed
            && $session->expires_at !== null
            && $session->expires_at->isPast()
        ) {
            $session->status = CheckoutSessionStatus::Expired;
            $session->save();
        }

        return $session->fresh() ?? $session;
    }
}
