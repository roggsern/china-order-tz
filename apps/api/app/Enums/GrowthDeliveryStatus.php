<?php

namespace App\Enums;

enum GrowthDeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Redeemed = 'redeemed';
    case Purchased = 'purchased';
    case Failed = 'failed';
}
