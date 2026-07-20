<?php

namespace App\Services\Promotions;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Promotion;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Procurement\SupplierCostService;
use App\Services\Promotions\DTOs\DiscountResolution;
use Illuminate\Validation\ValidationException;

/**
 * Central discount calculation. Must not be inlined into CartService / Order models.
 */
class DiscountResolver
{
    public function __construct(
        private readonly PromotionEligibilityService $eligibility,
        private readonly SupplierCostService $supplierCosts,
    ) {}

    /**
     * Resolve discounts for a cart. Optionally force a coupon code / promotion id.
     *
     * @param  array{admin_override?: bool, allow_low_margin?: bool}  $options
     */
    public function resolve(
        User $user,
        Cart $cart,
        string $subtotal,
        string $currency = 'TZS',
        ?string $couponCode = null,
        ?string $promotionId = null,
        array $options = [],
        string $shippingTotal = '0.00',
    ): DiscountResolution {
        $currency = strtoupper($currency);
        $shipping = number_format((float) $shippingTotal, 2, '.', '');
        $applications = [];
        $lineAllocations = [];
        $freeShipping = false;
        $primary = null;
        $discountTotal = '0.00';

        $candidates = $this->candidatePromotions($couponCode, $promotionId);

        if (($couponCode !== null && trim($couponCode) !== '') || $promotionId !== null) {
            if ($candidates === []) {
                throw ValidationException::withMessages([
                    'promotion' => ['Promotion code is invalid.'],
                ]);
            }
        }

        foreach ($candidates as $promotion) {
            if (! $this->eligibility->isEligible($promotion, $user, $cart, $subtotal)) {
                // Coupon explicitly requested — surface first failure.
                if ($couponCode !== null || $promotionId !== null) {
                    $failures = $this->eligibility->failures($promotion, $user, $cart, $subtotal);
                    throw ValidationException::withMessages([
                        'promotion' => $failures !== [] ? $failures : ['Promotion is not eligible.'],
                    ]);
                }
                continue;
            }

            $eligibleItems = $this->eligibility->eligibleItems($promotion, $cart);
            $eligibleSubtotal = $this->sumItems($eligibleItems);
            if (bccomp($eligibleSubtotal, '0.00', 2) <= 0
                && $promotion->discount_type !== PromotionDiscountType::FreeShipping) {
                continue;
            }

            $amount = $this->computeDiscountAmount($promotion, $eligibleSubtotal);
            if ($promotion->discount_type === PromotionDiscountType::FreeShipping) {
                $freeShipping = true;
                $amount = '0.00';
            }

            if (bccomp($amount, '0.00', 2) > 0 || $freeShipping) {
                $applications[] = [
                    'promotion_id' => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'promotion_code' => $promotion->code,
                    'discount_amount' => $amount,
                    'discount_type' => $promotion->discount_type->value,
                    'eligible_subtotal' => $eligibleSubtotal,
                ];
                $discountTotal = bcadd($discountTotal, $amount, 2);
                $lineAllocations = array_merge(
                    $lineAllocations,
                    $this->allocateToLines($eligibleItems, $eligibleSubtotal, $amount),
                );
                $primary ??= $promotion;

                // Coupons are exclusive with automatic stack limit.
                if ($promotion->type === PromotionType::Coupon) {
                    break;
                }
                if (count($applications) >= (int) config('promotions.max_automatic_stack', 1)) {
                    break;
                }
            }
        }

        if (bccomp($discountTotal, $subtotal, 2) > 0) {
            $discountTotal = $subtotal;
        }

        $margin = $this->estimateMargin($cart, $subtotal, $discountTotal);
        $reject = (bool) config('promotions.reject_low_margin', true);
        $threshold = (float) config('promotions.low_margin_threshold', 10);
        $adminOverride = (bool) ($options['admin_override'] ?? false)
            || (bool) ($options['allow_low_margin'] ?? false);
        $marginRejected = false;
        $marginMessage = null;

        if ($reject
            && $margin !== null
            && $margin < $threshold
            && bccomp($discountTotal, '0.00', 2) > 0
            && ! $adminOverride
        ) {
            $marginRejected = true;
            $marginMessage = sprintf(
                'Promotion rejected: estimated margin %.2f%% is below the %.2f%% threshold.',
                $margin,
                $threshold,
            );

            if ($couponCode !== null || $promotionId !== null) {
                throw ValidationException::withMessages([
                    'promotion' => [$marginMessage],
                    'margin' => [$marginMessage],
                ]);
            }

            // Automatic promotions that fail margin are skipped silently.
            return new DiscountResolution(
                subtotal: $subtotal,
                discountTotal: '0.00',
                shippingTotal: $shipping,
                currency: $currency,
                applications: [],
                lineAllocations: [],
                freeShipping: false,
                primaryPromotion: null,
                estimatedMarginPercentage: $margin,
                marginRejected: true,
                marginMessage: $marginMessage,
            );
        }

        return new DiscountResolution(
            subtotal: $subtotal,
            discountTotal: number_format((float) $discountTotal, 2, '.', ''),
            shippingTotal: $shipping,
            currency: $currency,
            applications: $applications,
            lineAllocations: $lineAllocations,
            freeShipping: $freeShipping,
            primaryPromotion: $primary,
            estimatedMarginPercentage: $margin,
            marginRejected: $marginRejected,
            marginMessage: $marginMessage,
        );
    }

    /**
     * Validate a coupon without applying (preview).
     *
     * @return array{promotion: Promotion, resolution: DiscountResolution, failures: list<string>}
     */
    public function validateCoupon(User $user, Cart $cart, string $code, string $subtotal, string $currency = 'TZS'): array
    {
        $promotion = Promotion::query()
            ->with('rules')
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->where('type', PromotionType::Coupon)
            ->first();

        if ($promotion === null) {
            throw ValidationException::withMessages([
                'code' => ['Promotion code is invalid.'],
            ]);
        }

        $failures = $this->eligibility->failures($promotion, $user, $cart, $subtotal);
        $shipping = $this->sumCartShipping($cart);
        $resolution = $failures === []
            ? $this->resolve($user, $cart, $subtotal, $currency, $code, null, [], $shipping)
            : new DiscountResolution(
                $subtotal,
                '0.00',
                $shipping,
                strtoupper($currency),
                [],
                [],
                false,
                $promotion,
                null,
                false,
                null,
            );

        return [
            'promotion' => $promotion,
            'resolution' => $resolution,
            'failures' => $failures,
        ];
    }

    /**
     * @return list<Promotion>
     */
    private function candidatePromotions(?string $couponCode, ?string $promotionId): array
    {
        if ($promotionId !== null) {
            $p = Promotion::query()->with('rules')->find($promotionId);

            return $p ? [$p] : [];
        }

        if ($couponCode !== null && trim($couponCode) !== '') {
            $p = Promotion::query()
                ->with('rules')
                ->whereRaw('UPPER(code) = ?', [strtoupper(trim($couponCode))])
                ->where('type', PromotionType::Coupon)
                ->first();

            return $p ? [$p] : [];
        }

        return Promotion::query()
            ->with('rules')
            ->where('type', PromotionType::Automatic)
            ->activeWindow()
            ->orderByDesc('value')
            ->limit(10)
            ->get()
            ->all();
    }

    private function computeDiscountAmount(Promotion $promotion, string $eligibleSubtotal): string
    {
        return match ($promotion->discount_type) {
            PromotionDiscountType::Percentage => number_format(
                min(
                    (float) $eligibleSubtotal,
                    round(((float) $eligibleSubtotal) * ((float) $promotion->value) / 100, 2),
                ),
                2,
                '.',
                '',
            ),
            PromotionDiscountType::FixedAmount => number_format(
                min((float) $eligibleSubtotal, (float) $promotion->value),
                2,
                '.',
                '',
            ),
            PromotionDiscountType::FreeShipping => '0.00',
        };
    }

    /**
     * @param  list<CartItem>  $items
     */
    private function sumItems(array $items): string
    {
        $sum = '0.00';
        foreach ($items as $item) {
            $unit = (string) ($item->price_snapshot ?? $item->unit_price ?? 0);
            $sum = bcadd($sum, bcmul($unit, (string) $item->quantity, 2), 2);
        }

        return $sum;
    }

    /**
     * @param  list<CartItem>  $items
     * @return list<array<string, mixed>>
     */
    private function allocateToLines(array $items, string $eligibleSubtotal, string $discountAmount): array
    {
        if (bccomp($eligibleSubtotal, '0.00', 2) <= 0 || bccomp($discountAmount, '0.00', 2) <= 0) {
            return [];
        }

        $allocations = [];
        $remaining = $discountAmount;
        $lastIndex = count($items) - 1;

        foreach ($items as $index => $item) {
            $unit = (string) ($item->price_snapshot ?? $item->unit_price ?? 0);
            $line = bcmul($unit, (string) $item->quantity, 2);
            if ($index === $lastIndex) {
                $share = $remaining;
            } else {
                $share = number_format(
                    round(((float) $line / (float) $eligibleSubtotal) * (float) $discountAmount, 2),
                    2,
                    '.',
                    '',
                );
                $remaining = bcsub($remaining, $share, 2);
            }

            $allocations[] = [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'original_amount' => $line,
                'discount_amount' => $share,
                'final_amount' => bcsub($line, $share, 2),
            ];
        }

        return $allocations;
    }

    private function estimateMargin(Cart $cart, string $subtotal, string $discountTotal): ?float
    {
        $revenue = (float) bcsub($subtotal, $discountTotal, 2);
        if ($revenue <= 0) {
            return 0.0;
        }

        $cost = 0.0;
        foreach ($cart->items as $item) {
            $variantId = $item->product_variant_id;
            if (! $variantId) {
                continue;
            }
            $qty = max(1, (int) $item->quantity);
            $unit = 0.0;
            $history = $this->supplierCosts->latestForVariant($variantId, $item->product?->supplier_id);
            if ($history !== null) {
                $unit = (float) $history->purchase_cost;
            } else {
                $mapping = SupplierProduct::query()
                    ->where('product_variant_id', $variantId)
                    ->where('is_active', true)
                    ->orderByDesc('updated_at')
                    ->first();
                $unit = $mapping
                    ? (float) $mapping->purchase_cost
                    : (float) ($item->product?->cost_price ?? 0);
            }
            $cost += $unit * $qty;
        }

        $profit = $revenue - $cost;

        return round(($profit / $revenue) * 100, 4);
    }

    /**
     * Company shipping (air/sea on cart lines) contributes to checkout totals.
     * Agent / TZ negotiated lines with no method contribute 0.00 — that is valid.
     */
    public function sumCartShipping(Cart $cart): string
    {
        $cart->loadMissing(['items.product']);
        $total = '0.00';

        foreach ($cart->items as $item) {
            if ($item->shipping_method === null) {
                continue;
            }

            if ($item->shipping_price === null) {
                $name = $item->product?->name ?? 'item';
                throw ValidationException::withMessages([
                    'shipping' => ["Shipping price is required for {$name} when a shipping method is selected."],
                ]);
            }

            $total = bcadd($total, bcmul((string) $item->shipping_price, (string) $item->quantity, 2), 2);
        }

        return $total;
    }
}
