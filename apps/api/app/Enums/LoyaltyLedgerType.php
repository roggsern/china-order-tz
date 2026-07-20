<?php

namespace App\Enums;

enum LoyaltyLedgerType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjust = 'adjust';
}
