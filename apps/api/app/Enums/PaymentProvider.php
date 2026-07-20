<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Nmb = 'nmb';
    case Selcom = 'selcom';
    case Stripe = 'stripe';
    case Flutterwave = 'flutterwave';
    case Pesapal = 'pesapal';
    case Crdb = 'crdb';
    case MixByYas = 'mix_by_yas';
    case Mpesa = 'mpesa';
    case AirtelMoney = 'airtel_money';
}
