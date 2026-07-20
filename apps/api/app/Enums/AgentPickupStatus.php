<?php

namespace App\Enums;

/**
 * Customer Agent pickup / handover lifecycle.
 * Owns pickup confirmation only — not company transport tracking.
 */
enum AgentPickupStatus: string
{
    case AwaitingPickup = 'awaiting_pickup';
    case PickupScheduled = 'pickup_scheduled';
    case AgentArrived = 'agent_arrived';
    case IdentityVerified = 'identity_verified';
    case AuthorizationVerified = 'authorization_verified';
    case GoodsVerified = 'goods_verified';
    case HandoverCompleted = 'handover_completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPickup => 'Awaiting pickup',
            self::PickupScheduled => 'Pickup scheduled',
            self::AgentArrived => 'Agent arrived',
            self::IdentityVerified => 'Identity verified',
            self::AuthorizationVerified => 'Authorization verified',
            self::GoodsVerified => 'Goods verified',
            self::HandoverCompleted => 'Handover completed',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    /**
     * Customer-facing tracking steps (no company transport tracking).
     *
     * @return list<self>
     */
    public static function trackingStatuses(): array
    {
        return [
            self::AwaitingPickup,
            self::PickupScheduled,
            self::AgentArrived,
            self::HandoverCompleted,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::HandoverCompleted, self::Cancelled], true);
    }
}
