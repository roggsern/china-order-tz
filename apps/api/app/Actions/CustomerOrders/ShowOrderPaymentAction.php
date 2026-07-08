<?php

namespace App\Actions\CustomerOrders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentPreparationService;

class ShowOrderPaymentAction
{
    public function __construct(
        private readonly PaymentPreparationService $paymentPreparationService,
    ) {}

    public function handle(Order $order, User $user): Payment
    {
        return $this->paymentPreparationService->show($order, $user);
    }
}
