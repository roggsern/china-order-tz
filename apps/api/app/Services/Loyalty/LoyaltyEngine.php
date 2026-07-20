<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyEarnRuleType;
use App\Enums\LoyaltyLedgerType;
use App\Enums\LoyaltyRewardType;
use App\Enums\OrderStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Events\Audit\LoyaltyPlatformAudit;
use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyEarnRule;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Crm\CustomerProfileService;
use App\Services\Crm\CustomerTimelineService;
use App\Enums\CustomerTimelineEventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Loyalty & Rewards engine — points, tiers, rewards.
 * Does not replace CRM, Promotion, or Pricing engines.
 */
class LoyaltyEngine
{
    public function __construct(
        private readonly LoyaltyNumberGenerator $numbers,
        private readonly CustomerProfileService $profiles,
        private readonly CustomerTimelineService $timeline,
    ) {}

    public function ensureAccountForUser(User $user): ?LoyaltyAccount
    {
        if (! $user->hasRole('customer')) {
            return null;
        }

        $profile = $this->profiles->ensureForUser($user);

        return $this->ensureAccount($profile);
    }

    public function ensureAccount(CustomerProfile $profile): LoyaltyAccount
    {
        $existing = LoyaltyAccount::query()->where('customer_profile_id', $profile->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $bronze = LoyaltyTier::query()->where('code', 'BRONZE')->where('is_active', true)->first()
            ?? LoyaltyTier::query()->where('is_active', true)->orderBy('sort_order')->first();

        $account = LoyaltyAccount::query()->create([
            'customer_profile_id' => $profile->id,
            'loyalty_number' => $this->numbers->generate(),
            'loyalty_tier_id' => $bronze?->id,
            'status' => LoyaltyAccountStatus::Active,
            'points_balance' => 0,
            'lifetime_points' => 0,
            'lifetime_redeemed' => 0,
            'tier_updated_at' => now(),
            'enrolled_at' => now(),
        ]);

        event(LoyaltyPlatformAudit::accountEnrolled($account));

        return $account->fresh(['tier', 'profile.user']) ?? $account;
    }

    /**
     * Award points after successful payment. Idempotent per order.
     */
    public function earnForPaidOrder(Order $order, ?Admin $admin = null): ?LoyaltyLedgerEntry
    {
        if ($order->user_id === null) {
            return null;
        }

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::PendingPayment, OrderStatus::Pending], true)) {
            return null;
        }

        $already = LoyaltyLedgerEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_type', LoyaltyLedgerType::Earn)
            ->exists();
        if ($already) {
            return null;
        }

        $user = $order->user ?? User::query()->find($order->user_id);
        if ($user === null) {
            return null;
        }

        $account = $this->ensureAccountForUser($user);
        if ($account === null || ! $account->isActive()) {
            return null;
        }

        $points = $this->calculateEarnPoints($order, $account);
        if ($points <= 0) {
            return null;
        }

        $expiryMonths = LoyaltyEarnRule::query()->activeWindow()
            ->where('rule_type', LoyaltyEarnRuleType::Spend)
            ->orderBy('priority')
            ->value('expiry_months');

        return $this->postLedger(
            account: $account,
            type: LoyaltyLedgerType::Earn,
            points: $points,
            reason: 'Points earned for order '.$order->order_number,
            orderId: $order->id,
            actorType: $admin ? 'admin' : 'system',
            actorId: $admin?->id,
            expiresAt: $expiryMonths ? now()->addMonths((int) $expiryMonths) : null,
            metadata: ['order_total' => (string) $order->total],
        );
    }

    public function calculateEarnPoints(Order $order, LoyaltyAccount $account): int
    {
        $order->loadMissing(['items.product']);
        $multiplier = (float) ($account->tier?->earn_multiplier ?? 1);
        $total = 0;

        $spendRules = LoyaltyEarnRule::query()->activeWindow()
            ->where('rule_type', LoyaltyEarnRuleType::Spend)
            ->orderBy('priority')
            ->get();

        foreach ($spendRules as $rule) {
            $spend = (float) ($rule->spend_amount ?? 0);
            $award = (int) ($rule->points_awarded ?? 0);
            if ($spend <= 0 || $award <= 0) {
                continue;
            }
            $units = (int) floor(((float) $order->total) / $spend);
            $total += $units * $award;
        }

        $productRules = LoyaltyEarnRule::query()->activeWindow()
            ->where('rule_type', LoyaltyEarnRuleType::Product)
            ->whereNotNull('product_id')
            ->get()
            ->keyBy('product_id');

        $categoryRules = LoyaltyEarnRule::query()->activeWindow()
            ->where('rule_type', LoyaltyEarnRuleType::Category)
            ->whereNotNull('category_id')
            ->get()
            ->keyBy('category_id');

        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;
            if ($productRules->has($item->product_id)) {
                $total += (int) $productRules->get($item->product_id)->points_awarded * $qty;
            }
            $categoryId = $item->product?->category_id;
            if ($categoryId && $categoryRules->has($categoryId)) {
                $total += (int) $categoryRules->get($categoryId)->points_awarded * $qty;
            }
        }

        $promoIds = DB::table('promotion_usages')->where('order_id', $order->id)->pluck('promotion_id');
        if ($promoIds->isNotEmpty()) {
            $bonus = LoyaltyEarnRule::query()->activeWindow()
                ->where('rule_type', LoyaltyEarnRuleType::PromotionBonus)
                ->whereIn('promotion_id', $promoIds->all())
                ->sum('bonus_points');
            $total += (int) $bonus;
        }

        return (int) max(0, round($total * $multiplier));
    }

    /**
     * Redeem reward → create Promotion coupon + ledger debit.
     *
     * @return array{redemption: LoyaltyRedemption, promotion_code: string, account: LoyaltyAccount}
     */
    public function redeemReward(
        LoyaltyAccount $account,
        LoyaltyReward $reward,
        string $channel = 'pos',
        ?Admin $admin = null,
    ): array {
        if (! $account->isActive()) {
            throw ValidationException::withMessages(['account' => ['Loyalty account is not active.']]);
        }

        if (! $reward->is_active) {
            throw ValidationException::withMessages(['reward' => ['Reward is not active.']]);
        }

        if ($account->points_balance < $reward->points_cost) {
            throw ValidationException::withMessages(['points' => ['Insufficient loyalty points.']]);
        }

        if ($reward->usage_limit !== null && $reward->redemption_count >= $reward->usage_limit) {
            throw ValidationException::withMessages(['reward' => ['Reward usage limit reached.']]);
        }

        if ($reward->per_customer_limit !== null) {
            $used = LoyaltyRedemption::query()
                ->where('loyalty_account_id', $account->id)
                ->where('loyalty_reward_id', $reward->id)
                ->where('status', '!=', 'cancelled')
                ->count();
            if ($used >= $reward->per_customer_limit) {
                throw ValidationException::withMessages(['reward' => ['Per-customer redemption limit reached.']]);
            }
        }

        return DB::transaction(function () use ($account, $reward, $channel, $admin) {
            /** @var LoyaltyAccount $locked */
            $locked = LoyaltyAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($locked->points_balance < $reward->points_cost) {
                throw ValidationException::withMessages(['points' => ['Insufficient loyalty points.']]);
            }

            $promotion = null;
            $code = null;
            if (in_array($reward->reward_type, [LoyaltyRewardType::DiscountVoucher, LoyaltyRewardType::SpecialOffer], true)
                && $reward->discount_type !== null
                && $reward->discount_value !== null) {
                $code = 'LY-'.strtoupper(Str::random(8));
                $promotion = Promotion::query()->create([
                    'name' => 'Loyalty: '.$reward->name,
                    'code' => $code,
                    'type' => PromotionType::Coupon,
                    'discount_type' => $reward->discount_type,
                    'value' => $reward->discount_value,
                    'currency' => 'TZS',
                    'status' => PromotionStatus::Active,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(30),
                    'usage_limit' => 1,
                    'per_customer_limit' => 1,
                    'minimum_order_amount' => null,
                    'created_by' => $admin?->id,
                ]);
            }

            $entry = $this->postLedger(
                account: $locked,
                type: LoyaltyLedgerType::Redeem,
                points: -1 * (int) $reward->points_cost,
                reason: 'Redeemed reward '.$reward->code,
                rewardId: $reward->id,
                promotionId: $promotion?->id,
                actorType: $admin ? 'admin' : 'customer',
                actorId: $admin?->id ?? $locked->customer_profile_id,
            );

            $redemption = LoyaltyRedemption::query()->create([
                'loyalty_account_id' => $locked->id,
                'loyalty_reward_id' => $reward->id,
                'loyalty_ledger_entry_id' => $entry->id,
                'promotion_id' => $promotion?->id,
                'promotion_code' => $code,
                'channel' => $channel,
                'status' => 'issued',
                'points_spent' => $reward->points_cost,
                'issued_at' => now(),
            ]);

            $reward->forceFill(['redemption_count' => (int) $reward->redemption_count + 1])->save();

            event(LoyaltyPlatformAudit::pointsRedeemed($entry, $admin));
            event(LoyaltyPlatformAudit::rewardIssued($redemption, $admin));

            $profile = $locked->profile;
            if ($profile) {
                $this->timeline->append(
                    $profile,
                    CustomerTimelineEventType::LoyaltyPointsRedeemed,
                    'Loyalty points redeemed',
                    sprintf('%d points redeemed for %s', $reward->points_cost, $reward->name),
                    LoyaltyRedemption::class,
                    $redemption->id,
                );
            }

            return [
                'redemption' => $redemption->fresh(['reward', 'promotion']),
                'promotion_code' => $code,
                'account' => $locked->fresh(['tier']),
            ];
        });
    }

    public function adjustPoints(
        LoyaltyAccount $account,
        int $points,
        string $reason,
        Admin $admin,
    ): LoyaltyLedgerEntry {
        if ($points === 0) {
            throw ValidationException::withMessages(['points' => ['Adjustment cannot be zero.']]);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Reason is required for manual adjustments.']]);
        }

        $entry = $this->postLedger(
            account: $account,
            type: LoyaltyLedgerType::Adjust,
            points: $points,
            reason: $reason,
            actorType: 'admin',
            actorId: $admin->id,
        );

        event(LoyaltyPlatformAudit::pointsAdjusted($entry, $admin));

        return $entry;
    }

    public function recalculateTier(LoyaltyAccount $account): LoyaltyAccount
    {
        $account->loadMissing('profile.metrics');
        $spend = (float) ($account->profile?->metrics?->total_spend ?? 0);
        $orders = (int) ($account->profile?->metrics?->total_orders ?? 0);
        $lifetime = (int) $account->lifetime_points;

        $tier = LoyaltyTier::query()
            ->where('is_active', true)
            ->where('min_lifetime_points', '<=', $lifetime)
            ->where('min_lifetime_spend', '<=', $spend)
            ->where('min_orders', '<=', $orders)
            ->orderByDesc('sort_order')
            ->first();

        if ($tier && $account->loyalty_tier_id !== $tier->id) {
            $before = $account->loyalty_tier_id;
            $account->forceFill([
                'loyalty_tier_id' => $tier->id,
                'tier_updated_at' => now(),
            ])->save();
            event(LoyaltyPlatformAudit::tierChanged($account->fresh(['tier']) ?? $account, $before));
            $profile = $account->profile;
            if ($profile) {
                $this->timeline->append(
                    $profile,
                    CustomerTimelineEventType::LoyaltyTierChanged,
                    'Loyalty tier changed',
                    'New tier: '.($account->fresh()?->tier?->name ?? 'updated'),
                    LoyaltyAccount::class,
                    $account->id,
                );
            }
        }

        return $account->fresh(['tier']) ?? $account;
    }

    /**
     * Expire due points (architecture support). Does not delete history.
     */
    public function expireDuePoints(?int $limit = 500): int
    {
        $entries = LoyaltyLedgerEntry::query()
            ->where('entry_type', LoyaltyLedgerType::Earn)
            ->whereNotNull('expires_at')
            ->whereNull('expired_at')
            ->where('expires_at', '<=', now())
            ->where('points', '>', 0)
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($entries as $earn) {
            DB::transaction(function () use ($earn, &$count) {
                $account = LoyaltyAccount::query()->whereKey($earn->loyalty_account_id)->lockForUpdate()->first();
                if ($account === null) {
                    return;
                }
                $debit = min((int) $earn->points, (int) $account->points_balance);
                if ($debit <= 0) {
                    $earn->forceFill(['expired_at' => now()])->save();

                    return;
                }
                $this->postLedger(
                    account: $account,
                    type: LoyaltyLedgerType::Expire,
                    points: -1 * $debit,
                    reason: 'Points expired',
                    actorType: 'system',
                    metadata: ['source_entry_id' => $earn->id],
                );
                $earn->forceFill(['expired_at' => now()])->save();
                $count++;
            });
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $active = LoyaltyAccount::query()->where('status', LoyaltyAccountStatus::Active)->count();
        $issued = (int) LoyaltyLedgerEntry::query()->where('entry_type', LoyaltyLedgerType::Earn)->sum('points');
        $redeemed = (int) abs((int) LoyaltyLedgerEntry::query()->where('entry_type', LoyaltyLedgerType::Redeem)->sum('points'));
        $tiers = LoyaltyAccount::query()
            ->selectRaw('loyalty_tier_id, COUNT(*) as cnt')
            ->groupBy('loyalty_tier_id')
            ->with('tier:id,code,name')
            ->get()
            ->map(fn ($r) => [
                'tier_id' => $r->loyalty_tier_id,
                'code' => $r->tier?->code,
                'name' => $r->tier?->name ?? 'Unassigned',
                'customers' => (int) $r->cnt,
            ])
            ->all();

        $top = LoyaltyAccount::query()
            ->with(['profile.user:id,name,email', 'tier:id,code,name'])
            ->orderByDesc('lifetime_points')
            ->limit(10)
            ->get()
            ->map(fn (LoyaltyAccount $a) => [
                'loyalty_number' => $a->loyalty_number,
                'customer' => $a->profile?->user?->name,
                'points_balance' => $a->points_balance,
                'lifetime_points' => $a->lifetime_points,
                'tier' => $a->tier?->name,
            ])
            ->all();

        return [
            'active_customers' => $active,
            'points_issued' => $issued,
            'points_redeemed' => $redeemed,
            'reward_redemptions' => LoyaltyRedemption::query()->count(),
            'tier_distribution' => $tiers,
            'top_customers' => $top,
        ];
    }

    private function postLedger(
        LoyaltyAccount $account,
        LoyaltyLedgerType $type,
        int $points,
        string $reason,
        ?string $orderId = null,
        ?string $ruleId = null,
        ?string $rewardId = null,
        ?string $promotionId = null,
        ?string $actorType = null,
        ?string $actorId = null,
        mixed $expiresAt = null,
        ?array $metadata = null,
    ): LoyaltyLedgerEntry {
        return DB::transaction(function () use (
            $account, $type, $points, $reason, $orderId, $ruleId, $rewardId,
            $promotionId, $actorType, $actorId, $expiresAt, $metadata,
        ) {
            /** @var LoyaltyAccount $locked */
            $locked = LoyaltyAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $balance = (int) $locked->points_balance + $points;
            if ($balance < 0) {
                throw ValidationException::withMessages(['points' => ['Insufficient loyalty points.']]);
            }

            $locked->forceFill([
                'points_balance' => $balance,
                'lifetime_points' => $type === LoyaltyLedgerType::Earn
                    ? (int) $locked->lifetime_points + max(0, $points)
                    : $locked->lifetime_points,
                'lifetime_redeemed' => $type === LoyaltyLedgerType::Redeem
                    ? (int) $locked->lifetime_redeemed + abs($points)
                    : $locked->lifetime_redeemed,
            ])->save();

            $entry = LoyaltyLedgerEntry::query()->create([
                'loyalty_account_id' => $locked->id,
                'entry_type' => $type,
                'points' => $points,
                'balance_after' => $balance,
                'reason' => $reason,
                'order_id' => $orderId,
                'loyalty_earn_rule_id' => $ruleId,
                'loyalty_reward_id' => $rewardId,
                'promotion_id' => $promotionId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            if ($type === LoyaltyLedgerType::Earn) {
                event(LoyaltyPlatformAudit::pointsEarned($entry));
                try {
                    $this->recalculateTier($locked->fresh() ?? $locked);
                } catch (\Throwable $e) {
                    Log::warning('loyalty.tier_recalc_failed', [
                        'account_id' => $locked->id,
                        'message' => $e->getMessage(),
                    ]);
                }
                $profile = $locked->fresh()?->profile;
                if ($profile) {
                    $this->timeline->append(
                        $profile,
                        CustomerTimelineEventType::LoyaltyPointsEarned,
                        'Loyalty points earned',
                        $reason,
                        LoyaltyLedgerEntry::class,
                        $entry->id,
                    );
                }
            }

            return $entry;
        });
    }
}
