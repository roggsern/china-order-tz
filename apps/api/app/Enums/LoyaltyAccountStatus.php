<?php

namespace App\Enums;

enum LoyaltyAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
