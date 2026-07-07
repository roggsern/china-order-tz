<?php

namespace App\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case CheckoutSession = 'checkout_session';
    case Converted = 'converted';
    case Abandoned = 'abandoned';
}
