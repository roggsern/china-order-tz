<?php

namespace App\Enums\CMS;

enum CmsNavigationItemType: string
{
    case Link = 'LINK';
    case Journey = 'JOURNEY';
    case MegaMenu = 'MEGA_MENU';
    case Group = 'GROUP';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'Link',
            self::Journey => 'Journey',
            self::MegaMenu => 'Mega Menu',
            self::Group => 'Group',
        };
    }
}
