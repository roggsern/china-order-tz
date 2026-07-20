<?php

namespace App\Enums;

enum PosPaymentHandler: string
{
    case CashWithChange = 'cash_with_change';
    case ManualConfirm = 'manual_confirm';
    case Gateway = 'gateway';
}
