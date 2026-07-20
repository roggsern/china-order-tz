<?php

namespace App\Enums\CMS;

enum CmsStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function isStorefrontEligible(): bool
    {
        return $this === self::Active;
    }
}
