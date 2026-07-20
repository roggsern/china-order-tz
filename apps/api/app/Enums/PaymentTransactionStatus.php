<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
