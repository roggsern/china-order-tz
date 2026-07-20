<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRedemption;

class LoyaltyPlatformAudit extends BusinessAuditEvent
{
    public static function accountEnrolled(LoyaltyAccount $account): self
    {
        return self::make(
            type: ActivityEventType::LoyaltyAccountEnrolled,
            actorType: ActivityActorType::System,
            actorId: null,
            subjectType: LoyaltyAccount::class,
            subjectId: $account->id,
            description: 'Loyalty account enrolled: '.$account->loyalty_number,
            newValues: [
                'loyalty_number' => $account->loyalty_number,
                'customer_profile_id' => $account->customer_profile_id,
            ],
        );
    }

    public static function pointsEarned(LoyaltyLedgerEntry $entry): self
    {
        return self::make(
            type: ActivityEventType::LoyaltyPointsEarned,
            actorType: ActivityActorType::System,
            actorId: null,
            subjectType: LoyaltyLedgerEntry::class,
            subjectId: $entry->id,
            description: sprintf('Loyalty points earned: %+d', $entry->points),
            newValues: [
                'points' => $entry->points,
                'balance_after' => $entry->balance_after,
                'order_id' => $entry->order_id,
            ],
        );
    }

    public static function pointsRedeemed(LoyaltyLedgerEntry $entry, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::LoyaltyPointsRedeemed,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: LoyaltyLedgerEntry::class,
            subjectId: $entry->id,
            description: sprintf('Loyalty points redeemed: %d', abs((int) $entry->points)),
            newValues: [
                'points' => $entry->points,
                'balance_after' => $entry->balance_after,
                'reward_id' => $entry->loyalty_reward_id,
            ],
        );
    }

    public static function pointsAdjusted(LoyaltyLedgerEntry $entry, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::LoyaltyPointsAdjusted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: LoyaltyLedgerEntry::class,
            subjectId: $entry->id,
            description: sprintf('Loyalty points adjusted: %+d (%s)', $entry->points, $entry->reason),
            newValues: [
                'points' => $entry->points,
                'reason' => $entry->reason,
                'balance_after' => $entry->balance_after,
            ],
        );
    }

    public static function tierChanged(LoyaltyAccount $account, ?string $previousTierId, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::LoyaltyTierChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: LoyaltyAccount::class,
            subjectId: $account->id,
            description: 'Loyalty tier changed',
            oldValues: ['loyalty_tier_id' => $previousTierId],
            newValues: ['loyalty_tier_id' => $account->loyalty_tier_id],
        );
    }

    public static function rewardIssued(LoyaltyRedemption $redemption, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::LoyaltyRewardIssued,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: LoyaltyRedemption::class,
            subjectId: $redemption->id,
            description: 'Loyalty reward issued',
            newValues: [
                'reward_id' => $redemption->loyalty_reward_id,
                'promotion_code' => $redemption->promotion_code,
                'points_spent' => $redemption->points_spent,
            ],
        );
    }
}
