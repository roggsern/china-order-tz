<?php

namespace App\Enums\CMS;

enum CmsHeroTextTheme: string
{
    case Light = 'LIGHT';
    case Dark = 'DARK';
    case Auto = 'AUTO';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
            self::Auto => 'Auto',
        };
    }
}
