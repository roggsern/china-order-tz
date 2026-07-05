<?php

namespace App\Actions\AdminPayments;

use App\Models\Payment;

class ShowPaymentAction
{
    public function handle(Payment $payment): Payment
    {
        return $payment->load(['order']);
    }
}
