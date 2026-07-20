<?php

namespace App\Services\Promotions;

use App\Enums\PromotionRuleType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Models\User;
use App\Services\Commerce\CommerceChannelResolver;

/**
 * Validates whether a promotion can apply to a given cart/customer context.
 */
class PromotionEligibilityService
{
    public function __construct(
        private readonly PromotionUsageService $usages,
        private readonly CommerceChannelResolver $channels,
    ) {}

    /**
     * @return list<string> empty = eligible
     */
    public function failures(
        Promotion $promotion,
        User $user,
        Cart $cart,
        string $subtotal,
        bool $ignoreUsageLimits = false,
    ): array {
        $failures = [];

        if ($promotion->status !== PromotionStatus::Active) {
            $failures[] = 'Promotion is not active.';
        }

        if (! $promotion->isWithinDateWindow()) {
            $failures[] = 'Promotion is outside its valid date range.';
        }

        if ($promotion->minimum_order_amount !== null
            && bccomp($subtotal, (string) $promotion->minimum_order_amount, 2) < 0) {
            $failures[] = 'Cart does not meet the minimum order amount.';
        }

        if (! $ignoreUsageLimits) {
            if ($promotion->usage_limit !== null
                && $this->usages->totalUsageCount($promotion) >= $promotion->usage_limit) {
                $failures[] = 'Promotion usage limit has been reached.';
            }

            if ($promotion->per_customer_limit !== null
                && $this->usages->customerUsageCount($promotion, $user) >= $promotion->per_customer_limit) {
                $failures[] = 'You have reached the per-customer usage limit for this promotion.';
            }
        }

        $promotion->loadMissing('rules');
        foreach ($promotion->rules as $rule) {
            if (! $this->rulePasses($rule, $user, $cart, $subtotal)) {
                $failures[] = 'Cart does not satisfy promotion rule: '.$rule->rule_type->value;
            }
        }

        return array_values(array_unique($failures));
    }

    public function isEligible(
        Promotion $promotion,
        User $user,
        Cart $cart,
        string $subtotal,
        bool $ignoreUsageLimits = false,
    ): bool {
        return $this->failures($promotion, $user, $cart, $subtotal, $ignoreUsageLimits) === [];
    }

    /**
     * Cart lines that match product/variant/category rules (or all lines if no catalog rules).
     *
     * @return list<CartItem>
     */
    public function eligibleItems(Promotion $promotion, Cart $cart): array
    {
        $promotion->loadMissing('rules');
        $catalogRules = $promotion->rules->filter(fn (PromotionRule $r) => in_array(
            $r->rule_type,
            [PromotionRuleType::Product, PromotionRuleType::Variant, PromotionRuleType::Category],
            true,
        ));

        if ($catalogRules->isEmpty()) {
            return $cart->items->all();
        }

        return $cart->items->filter(function (CartItem $item) use ($catalogRules) {
            foreach ($catalogRules as $rule) {
                if ($this->itemMatchesCatalogRule($rule, $item)) {
                    return true;
                }
            }

            return false;
        })->values()->all();
    }

    private function rulePasses(PromotionRule $rule, User $user, Cart $cart, string $subtotal): bool
    {
        $value = $rule->rule_value ?? [];

        return match ($rule->rule_type) {
            PromotionRuleType::Product,
            PromotionRuleType::Variant,
            PromotionRuleType::Category => $cart->items->contains(
                fn (CartItem $item) => $this->itemMatchesCatalogRule($rule, $item)
            ),
            PromotionRuleType::CommerceChannel => $this->channelMatches($cart, $value),
            PromotionRuleType::CustomerTag => $this->customerHasTag($user, $value),
            PromotionRuleType::CartTotal => $this->cartTotalMatches($subtotal, $value),
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function itemMatchesCatalogRule(PromotionRule $rule, CartItem $item): bool
    {
        $ids = $this->idList($rule->rule_value ?? []);

        return match ($rule->rule_type) {
            PromotionRuleType::Product => in_array((string) $item->product_id, $ids, true),
            PromotionRuleType::Variant => in_array((string) $item->product_variant_id, $ids, true),
            PromotionRuleType::Category => in_array((string) ($item->product?->category_id ?? ''), $ids, true),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function channelMatches(Cart $cart, array $value): bool
    {
        try {
            $channel = $this->channels->assertCartSingleChannel($cart);
        } catch (\Throwable) {
            return false;
        }

        $ids = $this->idList($value);
        $codes = array_map('strtoupper', $this->stringList($value, 'codes'));

        if ($ids !== [] && in_array((string) $channel->id, $ids, true)) {
            return true;
        }

        if ($codes !== [] && in_array(strtoupper((string) $channel->code), $codes, true)) {
            return true;
        }

        return $ids === [] && $codes === [];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function customerHasTag(User $user, array $value): bool
    {
        $slugs = array_map('strtolower', $this->stringList($value, 'slugs'));
        $ids = $this->idList($value);

        $profile = $user->customerProfile;
        if ($profile === null) {
            return false;
        }

        return $profile->tags()
            ->where(function ($q) use ($slugs, $ids) {
                if ($ids !== []) {
                    $q->whereIn('customer_tags.id', $ids);
                }
                if ($slugs !== []) {
                    $q->orWhereIn('customer_tags.slug', $slugs);
                }
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function cartTotalMatches(string $subtotal, array $value): bool
    {
        $min = isset($value['min']) ? (string) $value['min'] : null;
        $max = isset($value['max']) ? (string) $value['max'] : null;

        if ($min !== null && bccomp($subtotal, $min, 2) < 0) {
            return false;
        }
        if ($max !== null && bccomp($subtotal, $max, 2) > 0) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<string>
     */
    private function idList(array $value): array
    {
        $raw = $value['ids'] ?? $value['id'] ?? [];
        if (! is_array($raw)) {
            $raw = [$raw];
        }

        return array_values(array_filter(array_map('strval', $raw)));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<string>
     */
    private function stringList(array $value, string $key): array
    {
        $raw = $value[$key] ?? $value['slug'] ?? $value['code'] ?? [];
        if (! is_array($raw)) {
            $raw = [$raw];
        }

        return array_values(array_filter(array_map('strval', $raw)));
    }
}
