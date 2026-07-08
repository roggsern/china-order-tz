<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Initiated = 'initiated';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
