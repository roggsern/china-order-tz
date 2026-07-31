<?php

namespace App\Enums;

enum StoreOperationalScope: string
{
    case StoreManager = 'store_manager';
    case StoreOperator = 'store_operator';
    case StoreViewer = 'store_viewer';

    public function label(): string
    {
        return match ($this) {
            self::StoreManager => 'Store Manager',
            self::StoreOperator => 'Store Operator',
            self::StoreViewer => 'Store Viewer',
        };
    }

    public function canManageStore(): bool
    {
        return match ($this) {
            self::StoreManager, self::StoreOperator => true,
            self::StoreViewer => false,
        };
    }

    public function canManageTeam(): bool
    {
        return $this === self::StoreManager;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
