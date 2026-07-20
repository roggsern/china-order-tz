<?php

namespace App\Enums\CMS;

enum CmsHeroContentAlignment: string
{
    case Left = 'LEFT';
    case Center = 'CENTER';
    case Right = 'RIGHT';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left',
            self::Center => 'Center',
            self::Right => 'Right',
        };
    }
}
