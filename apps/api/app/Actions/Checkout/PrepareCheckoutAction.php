<?php

namespace App\Actions\Checkout;

use App\Models\User;
use App\Services\Checkout\CheckoutService;

class PrepareCheckoutAction
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        return $this->checkoutService->prepare($user);
    }
}
