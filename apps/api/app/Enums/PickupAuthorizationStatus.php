<?php

namespace App\Enums;

/**
 * Pickup authorization for Customer Agent logistics.
 * Owned by CustomerAgentWorkflowEngine (logistics).
 */
enum PickupAuthorizationStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending authorization',
            self::Authorized => 'Authorized',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    public function isValidForPickup(): bool
    {
        return $this === self::Authorized;
    }
}
