<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Mpesa = 'mpesa';
    case Card = 'card';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
}
