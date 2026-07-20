<?php

namespace App\Enums;

enum GrowthStage: string
{
    case New = 'new';
    case Active = 'active';
    case Vip = 'vip';
    case Inactive = 'inactive';
    case Winback = 'winback';
}
