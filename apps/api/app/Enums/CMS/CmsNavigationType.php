<?php

namespace App\Enums\CMS;

enum CmsNavigationType: string
{
    case Primary = 'PRIMARY';
    case Footer = 'FOOTER';
    case Mobile = 'MOBILE';
    case Utility = 'UTILITY';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Footer => 'Footer',
            self::Mobile => 'Mobile',
            self::Utility => 'Utility',
        };
    }
}
