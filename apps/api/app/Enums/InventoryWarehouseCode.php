<?php

namespace App\Enums;

/**
 * Canonical warehouse_code values for variant_inventories.
 * Only Tanzania sellable codes may be resolved for customer commerce.
 */
enum InventoryWarehouseCode: string
{
    case Main = 'MAIN';
    case China = 'CHINA';
    case InTransit = 'IN_TRANSIT';

    public function isSellableForCommerce(): bool
    {
        return $this === self::Main;
    }

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Tanzania sellable',
            self::China => 'China warehouse',
            self::InTransit => 'In transit (China → Tanzania)',
        };
    }

    public static function tryNormalize(string $code): ?self
    {
        return self::tryFrom(strtoupper(trim($code)));
    }

    public static function isSellableCommerceCode(string $code): bool
    {
        $normalized = self::tryNormalize($code);

        return $normalized?->isSellableForCommerce() ?? false;
    }

    /**
     * @return list<string>
     */
    public static function nonSellableCodes(): array
    {
        return [
            self::China->value,
            self::InTransit->value,
        ];
    }
}
