<?php

namespace App\Enums\CMS;

enum CmsNavigationVisibility: string
{
    case Public = 'PUBLIC';
    case AuthOnly = 'AUTH_ONLY';
    case GuestOnly = 'GUEST_ONLY';
    case AdminPreview = 'ADMIN_PREVIEW';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::AuthOnly => 'Authenticated only',
            self::GuestOnly => 'Guest only',
            self::AdminPreview => 'Admin preview',
        };
    }

    /**
     * @param  'guest'|'authenticated'|'admin_preview'  $audience
     */
    public function visibleTo(string $audience): bool
    {
        return match ($this) {
            self::Public => true,
            self::AuthOnly => in_array($audience, ['authenticated', 'admin_preview'], true),
            self::GuestOnly => $audience === 'guest',
            self::AdminPreview => $audience === 'admin_preview',
        };
    }
}
