<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Mpesa = 'mpesa';
    case Nmb = 'nmb';
    case Card = 'card';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
}
