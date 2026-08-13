<?php

namespace App\Services\Checkout;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Cart\ResolveCartPurchasable;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\Inventory\DTOs\ReservationContext;
use App\Services\Inventory\ReservationService;
use App\Services\Promotions\DiscountResolver;
use App\Services\Promotions\DTOs\DiscountResolution;
use App\Services\Storefront\VisitorIdentityService;
use App\Support\Http\ApiResponse;
use Illuminate\Support\Facades\DB;

/**
 * Checkout Orchestrator — validates Cart + Pricing + Inventory,
 * creates a CheckoutSession snapshot, then stops.
 * Discount calculation is delegated to DiscountResolver (Promotion Engine).
 * Soft-holds stock via ReservationService (ADR 055); payment commitment unchanged.
 */
class CheckoutOrchestrator
{
    public const SESSION_TTL_MINUTES = 30;

    public function __construct(
        private readonly CartService $cartService,
        private readonly ResolveCartPurchasable $resolveCartPurchasable,
        private readonly CommerceChannelResolver $commerceChannelResolver,
        private readonly DiscountResolver $discountResolver,
        private readonly ReservationService $reservationService,
        private readonly VisitorIdentityService $visitorIdentity,
    ) {}

    /**
     * @param  array{visitor_uuid?: string|null, session_id?: string|null}  $attribution
     */
    public function start(User $user, array $attribution = []): CheckoutSession
    {
        $cart = $this->resolveCart($user);
        $totals = $this->validateCart($cart, $user);
        $storefrontAttribution = $this->resolveStorefrontAttribution($user, $attribution);

        return DB::transaction(function () use ($user, $cart, $totals, $storefrontAttribution): CheckoutSession {
            $this->expireOpenSessionsForCart($user, $cart);

            $session = CheckoutSession::query()->create([
                'user_id' => $user->id,
                'storefront_visitor_id' => $storefrontAttribution['visitor_id'] ?? null,
                'storefront_session_id' => $storefrontAttribution['session_id'] ?? null,
                'cart_id' => $cart->id,
                'status' => CheckoutSessionStatus::Draft,
                'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
            ]);

            $session->applyTotals($totals);
            $session->status = CheckoutSessionStatus::Validated;
            $session->save();

            $this->reservationService->reserve(new ReservationContext(
                checkoutSession: $session,
                cart: $cart,
                expiresAt: $session->expires_at,
                source: 'checkout_start',
            ));

            return $this->loadSession($session);
        });
    }

    public function show(User $user, CheckoutSession $session): CheckoutSession
    {
        $this->authorizeSession($user, $session);
        $session = $this->markExpiredIfNeeded($session);

        if ($session->isExpired()) {
            ApiResponse::throwCodedValidation([
                'session' => ['Checkout session has expired. Start a new checkout.'],
            ]);
        }

        return $this->loadSession($session);
    }

    public function refresh(User $user, CheckoutSession $session): CheckoutSession
    {
        $this->authorizeSession($user, $session);
        $session = $this->markExpiredIfNeeded($session);

        if ($session->status === CheckoutSessionStatus::Completed) {
            ApiResponse::throwCodedValidation([
                'session' => ['Checkout session is already completed.'],
            ]);
        }

        if ($session->isExpired()) {
            ApiResponse::throwCodedValidation([
                'session' => ['Checkout session has expired. Start a new checkout.'],
            ]);
        }

        $cart = $this->cartService->loadCart($session->cart()->firstOrFail());
        $this->assertCartOwnedByUser($user, $cart);

        $totals = $this->validateCart(
            $cart,
            $user,
            $session->applied_promotion_code,
            $session->promotion_id,
        );

        $session->applyTotals($totals);
        $session->status = CheckoutSessionStatus::Validated;
        $session->expires_at = now()->addMinutes(self::SESSION_TTL_MINUTES);
        $session->save();

        $this->reservationService->syncForCheckout($session, $cart);
        $this->reservationService->extend(new ReservationContext(
            checkoutSession: $session,
            cart: $cart,
            expiresAt: $session->expires_at,
            source: 'checkout_refresh',
        ));

        return $this->loadSession($session);
    }

    /**
     * Apply a coupon code to an open checkout session and recalculate totals.
     */
    public function applyPromotion(User $user, CheckoutSession $session, string $code): CheckoutSession
    {
        $this->authorizeSession($user, $session);
        $session = $this->markExpiredIfNeeded($session);

        if ($session->isExpired() || $session->status === CheckoutSessionStatus::Completed) {
            ApiResponse::throwCodedValidation([
                'session' => ['Checkout session cannot accept a promotion.'],
            ]);
        }

        $cart = $this->cartService->loadCart($session->cart()->firstOrFail());
        $base = $this->validateCartLines($cart);
        $resolution = $this->discountResolver->resolve(
            $user,
            $cart,
            $base['subtotal'],
            $base['currency'],
            $code,
        );

        $totals = $this->totalsFromResolution($base, $resolution);
        $session->applyTotals($totals);
        $session->status = CheckoutSessionStatus::Validated;
        $session->expires_at = now()->addMinutes(self::SESSION_TTL_MINUTES);
        $session->save();

        return $this->loadSession($session);
    }

    public function clearPromotion(User $user, CheckoutSession $session): CheckoutSession
    {
        $this->authorizeSession($user, $session);
        $session->promotion_id = null;
        $session->applied_promotion_code = null;
        $session->discount_breakdown = null;
        $session->save();

        return $this->refresh($user, $session);
    }

    public function cancel(User $user, CheckoutSession $session): void
    {
        $this->authorizeSession($user, $session);

        if ($session->status === CheckoutSessionStatus::Completed) {
            ApiResponse::throwCodedValidation([
                'session' => ['Completed checkout sessions cannot be cancelled.'],
            ]);
        }

        $this->reservationService->release(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'checkout_cancel',
        ));

        $session->delete();
    }

    public function markCompleted(CheckoutSession $session): CheckoutSession
    {
        // Keep soft-holds until payment convertToCommit (ADR 055 / 2A-3C-2).
        // Cancel / expire / replace still release via ReservationService.
        $session->status = CheckoutSessionStatus::Completed;
        $session->save();

        return $session->fresh() ?? $session;
    }

    /**
     * Validate cart lines against Cart Engine + Pricing + Inventory + Promotion Engine.
     *
     * @return array{
     *     subtotal: string,
     *     discount_total: string,
     *     tax_total: string,
     *     shipping_total: string,
     *     currency: string,
     *     grand_total: string,
     *     discount_breakdown?: array<string, mixed>|null,
     *     promotion_id?: string|null,
     *     applied_promotion_code?: string|null,
     *     resolution?: DiscountResolution
     * }
     */
    public function validateCart(
        Cart $cart,
        ?User $user = null,
        ?string $couponCode = null,
        ?string $promotionId = null,
    ): array {
        $base = $this->validateCartLines($cart);
        $shipping = $this->discountResolver->sumCartShipping($cart);

        if ($user === null) {
            return $this->calculateTotals($base['subtotal'], $base['currency'], $shipping);
        }

        $resolution = $this->discountResolver->resolve(
            $user,
            $cart,
            $base['subtotal'],
            $base['currency'],
            $couponCode,
            $promotionId,
            [],
            $shipping,
        );

        return $this->totalsFromResolution($base, $resolution);
    }

    /**
     * Last successful DiscountResolution for order snapshot recording.
     */
    public function resolveDiscountsForSession(User $user, CheckoutSession $session): DiscountResolution
    {
        $cart = $this->cartService->loadCart($session->cart()->firstOrFail());
        $base = $this->validateCartLines($cart);
        $shipping = $this->discountResolver->sumCartShipping($cart);

        return $this->discountResolver->resolve(
            $user,
            $cart,
            $base['subtotal'],
            $base['currency'],
            $session->applied_promotion_code,
            $session->promotion_id,
            [],
            $shipping,
        );
    }

    /**
     * @return array{subtotal: string, currency: string}
     */
    public function validateCartLines(Cart $cart): array
    {
        $cart = $this->cartService->loadCart($cart);

        if ($cart->isEmpty()) {
            ApiResponse::throwCodedValidation([
                'cart' => ['Cart is empty.'],
            ]);
        }

        $this->commerceChannelResolver->assertCartSingleChannel($cart);

        $currency = strtoupper((string) ($cart->currency ?: 'TZS'));
        $subtotal = '0.00';

        /** @var CartItem $item */
        foreach ($cart->items as $item) {
            if ($item->quantity < 1) {
                ApiResponse::throwCodedValidation([
                    'quantity' => ['Quantity must be at least 1.'],
                ]);
            }

            $itemCurrency = strtoupper((string) ($item->currency ?: $currency));
            if ($itemCurrency !== $currency) {
                ApiResponse::throwCodedValidation([
                    'currency' => ['Cart currency is inconsistent across items.'],
                ]);
            }

            // RC1-C1 — always re-price via CommercePricingResolver (never trust stale snapshots).
            $resolved = $this->resolveCartPurchasable->handle(
                $item->product_id,
                $item->product_variant_id,
                (int) $item->quantity,
                $currency,
                null,
            );

            $unit = (string) $resolved['unit_price'];
            $subtotal = bcadd($subtotal, bcmul($unit, (string) $item->quantity, 2), 2);

            $item->forceFill([
                'price_snapshot' => $resolved['unit_price'],
                'unit_price' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
            ])->save();
        }

        return [
            'subtotal' => $subtotal,
            'currency' => $currency,
        ];
    }

    /**
     * Authoritative totals without promotions.
     * Shipping is company air/sea line charges only; agent/TZ with no method = 0.00.
     *
     * @return array{
     *     subtotal: string,
     *     discount_total: string,
     *     tax_total: string,
     *     shipping_total: string,
     *     currency: string,
     *     grand_total: string
     * }
     */
    public function calculateTotals(
        string $subtotal,
        string $currency = 'TZS',
        string $shipping = '0.00',
    ): array {
        $discount = '0.00';
        $tax = '0.00';
        $shipping = number_format((float) $shipping, 2, '.', '');
        $grand = bcsub(
            bcadd(bcadd($subtotal, $shipping, 2), $tax, 2),
            $discount,
            2,
        );

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'shipping_total' => $shipping,
            'currency' => strtoupper($currency),
            'grand_total' => $grand,
        ];
    }

    public function loadSession(CheckoutSession $session): CheckoutSession
    {
        return $session->load([
            'cart.items.product.brand',
            'cart.items.product.category',
            'cart.items.product.images',
            'cart.items.variant.attributeValues.attribute',
            'cart.items.variant.catalogAttributeValues.attribute',
            'cart.items.variant.catalogAttributeValues.option',
            'cart.items.variant.inventories',
            'cart.items.variant.prices',
            'user',
            'promotion',
        ]);
    }

    /**
     * @param  array{subtotal: string, currency: string}  $base
     * @return array<string, mixed>
     */
    private function totalsFromResolution(array $base, DiscountResolution $resolution): array
    {
        $tax = '0.00';
        $shipping = $resolution->freeShipping ? '0.00' : $resolution->shippingTotal;

        return [
            'subtotal' => $base['subtotal'],
            'discount_total' => $resolution->discountTotal,
            'tax_total' => $tax,
            'shipping_total' => $shipping,
            'currency' => $resolution->currency,
            'grand_total' => $resolution->grandTotal($tax),
            'discount_breakdown' => $resolution->toBreakdown(),
            'promotion_id' => $resolution->primaryPromotion?->id,
            'applied_promotion_code' => $resolution->primaryPromotion?->code,
            'resolution' => $resolution,
        ];
    }

    private function resolveCart(User $user): Cart
    {
        $buyNow = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::CheckoutSession)
            ->withCount('items')
            ->first();

        if ($buyNow !== null && $buyNow->items_count > 0) {
            return $this->cartService->loadCart($buyNow);
        }

        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::Active)
            ->first();

        if ($cart === null) {
            ApiResponse::throwCodedValidation([
                'cart' => ['Active cart not found.'],
            ]);
        }

        return $this->cartService->loadCart($cart);
    }

    private function authorizeSession(User $user, CheckoutSession $session): void
    {
        if ($session->user_id !== $user->id) {
            abort(404);
        }
    }

    private function assertCartOwnedByUser(User $user, Cart $cart): void
    {
        if ($cart->user_id !== $user->id) {
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

    private function expireOpenSessionsForCart(User $user, Cart $cart): void
    {
        $sessions = CheckoutSession::query()
            ->where('user_id', $user->id)
            ->where('cart_id', $cart->id)
            ->whereIn('status', [
                CheckoutSessionStatus::Draft->value,
                CheckoutSessionStatus::Validated->value,
            ])
            ->get();

        foreach ($sessions as $open) {
            $this->reservationService->expire(new ReservationContext(
                checkoutSession: $open,
                cart: $cart,
                source: 'checkout_replaced',
            ));

            $open->forceFill([
                'status' => CheckoutSessionStatus::Expired,
                'expires_at' => now(),
            ])->save();
        }
    }

    /**
     * @param  array{visitor_uuid?: string|null, session_id?: string|null}  $attribution
     * @return array{visitor_id?: string, session_id?: string}
     */
    private function resolveStorefrontAttribution(User $user, array $attribution): array
    {
        $visitorUuid = $attribution['visitor_uuid'] ?? null;
        $sessionId = $attribution['session_id'] ?? null;

        if (! is_string($visitorUuid) || trim($visitorUuid) === '') {
            return [];
        }

        $identity = $this->visitorIdentity->identify($visitorUuid, $sessionId, $user);

        return [
            'visitor_id' => $identity['visitor_id'],
            'session_id' => $identity['session_id'],
        ];
    }
}
