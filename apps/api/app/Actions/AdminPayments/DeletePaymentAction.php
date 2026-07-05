<?php

namespace App\Actions\AdminPayments;

use App\Models\Payment;

class DeletePaymentAction
{
    public function handle(Payment $payment): void
    {
        $payment->delete();
    }
}
