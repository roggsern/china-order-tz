<?php

namespace App\Enums;

enum CheckoutSessionStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Expired = 'expired';
    case Completed = 'completed';
}
