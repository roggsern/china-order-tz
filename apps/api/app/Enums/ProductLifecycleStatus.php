<?php

namespace App\Enums;

enum ProductLifecycleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OutOfStock = 'out_of_stock';
    case Archived = 'archived';

    public function isListed(): bool
    {
        return in_array($this, [self::Active, self::OutOfStock], true);
    }

    public function isPurchasable(): bool
    {
        return $this === self::Active;
    }

    public function syncIsActiveFlag(): bool
    {
        return $this->isListed();
    }

    public static function fromLegacyActive(bool $isActive): self
    {
        return $isActive ? self::Active : self::Draft;
    }

    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim(str_replace('-', '_', $value)));

        return match ($normalized) {
            'draft' => self::Draft,
            'active', '1', 'true', 'yes' => self::Active,
            'out_of_stock', 'outofstock', 'oos' => self::OutOfStock,
            'archived', 'hidden' => self::Archived,
            default => self::tryFrom($normalized),
        };
    }
}
