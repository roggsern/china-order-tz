<?php

namespace App\Actions\CustomerOrders;

use App\Enums\PaymentMethod;
use App\Http\Requests\Orders\PrepareOrderPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentPreparationService;

class PrepareOrderPaymentAction
{
    public function __construct(
        private readonly PaymentPreparationService $paymentPreparationService,
    ) {}

    public function handle(Order $order, PrepareOrderPaymentRequest $request, User $user): Payment
    {
        $method = PaymentMethod::from($request->validated('payment_method'));

        return $this->paymentPreparationService->prepare($order, $user, $method);
    }
}
