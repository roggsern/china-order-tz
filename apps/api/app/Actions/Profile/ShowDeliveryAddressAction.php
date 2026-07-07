<?php

namespace App\Actions\Profile;

use App\Models\DeliveryAddress;
use App\Models\User;
use App\Services\Profile\DeliveryAddressService;

class ShowDeliveryAddressAction
{
    public function __construct(
        private readonly DeliveryAddressService $deliveryAddressService,
    ) {}

    public function handle(User $user): DeliveryAddress
    {
        return $this->deliveryAddressService->show($user);
    }
}
