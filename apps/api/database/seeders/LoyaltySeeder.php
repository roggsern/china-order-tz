<?php

namespace Database\Seeders;

use App\Enums\LoyaltyEarnRuleType;
use App\Enums\LoyaltyRewardType;
use App\Enums\PromotionDiscountType;
use App\Models\LoyaltyEarnRule;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTier;
use Illuminate\Database\Seeder;

class LoyaltySeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'code' => 'BRONZE',
                'name' => 'Bronze',
                'description' => 'Starter loyalty tier',
                'sort_order' => 10,
                'min_lifetime_points' => 0,
                'min_lifetime_spend' => 0,
                'min_orders' => 0,
                'earn_multiplier' => 1,
                'benefits' => ['Standard earn rate'],
            ],
            [
                'code' => 'SILVER',
                'name' => 'Silver',
                'description' => 'Active shoppers',
                'sort_order' => 20,
                'min_lifetime_points' => 500,
                'min_lifetime_spend' => 100000,
                'min_orders' => 3,
                'earn_multiplier' => 1.1,
                'benefits' => ['10% bonus earn'],
            ],
            [
                'code' => 'GOLD',
                'name' => 'Gold',
                'description' => 'High-value customers',
                'sort_order' => 30,
                'min_lifetime_points' => 2000,
                'min_lifetime_spend' => 500000,
                'min_orders' => 10,
                'earn_multiplier' => 1.25,
                'benefits' => ['25% bonus earn'],
            ],
            [
                'code' => 'PLATINUM',
                'name' => 'Platinum',
                'description' => 'VIP customers',
                'sort_order' => 40,
                'min_lifetime_points' => 5000,
                'min_lifetime_spend' => 1500000,
                'min_orders' => 25,
                'earn_multiplier' => 1.5,
                'benefits' => ['50% bonus earn', 'VIP priority'],
            ],
        ];

        foreach ($tiers as $tier) {
            LoyaltyTier::query()->updateOrCreate(
                ['code' => $tier['code']],
                array_merge($tier, ['is_active' => true]),
            );
        }

        LoyaltyEarnRule::query()->updateOrCreate(
            ['code' => 'SPEND_1000_10'],
            [
                'name' => 'Every 1,000 TZS = 10 points',
                'rule_type' => LoyaltyEarnRuleType::Spend,
                'is_active' => true,
                'priority' => 10,
                'spend_amount' => 1000,
                'points_awarded' => 10,
                'expiry_months' => 12,
            ],
        );

        LoyaltyReward::query()->updateOrCreate(
            ['code' => 'REWARD_5PCT'],
            [
                'name' => '5% Discount Voucher',
                'description' => 'Redeem points for a one-time 5% coupon via Promotion Engine',
                'reward_type' => LoyaltyRewardType::DiscountVoucher,
                'is_active' => true,
                'points_cost' => 100,
                'discount_type' => PromotionDiscountType::Percentage,
                'discount_value' => 5,
                'usage_limit' => null,
                'per_customer_limit' => 5,
                'channels' => ['pos', 'storefront'],
            ],
        );
    }
}
