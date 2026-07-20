<?php

namespace App\Enums\CMS;

enum CmsFeaturedDisplayStyle: string
{
    case Grid = 'GRID';
    case Carousel = 'CAROUSEL';
    case List = 'LIST';
    case Compact = 'COMPACT';

    public function label(): string
    {
        return match ($this) {
            self::Grid => 'Grid',
            self::Carousel => 'Carousel',
            self::List => 'List',
            self::Compact => 'Compact',
        };
    }
}
