<?php

namespace App\Support\Admin;

enum RolePermissionImpactWarning: string
{
    case HighRiskPermissionAdded = 'HIGH_RISK_PERMISSION_ADDED';
    case AdminAccessReduction = 'ADMIN_ACCESS_REDUCTION';
    case MultipleUsersAffected = 'MULTIPLE_USERS_AFFECTED';

    public function label(): string
    {
        return match ($this) {
            self::HighRiskPermissionAdded => 'High risk permission added',
            self::AdminAccessReduction => 'Admin access reduction',
            self::MultipleUsersAffected => 'Multiple users affected',
        };
    }
}
