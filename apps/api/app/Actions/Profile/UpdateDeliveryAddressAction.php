<?php

namespace App\Actions\Profile;

use App\Http\Requests\Profile\UpdateDeliveryAddressRequest;
use App\Models\DeliveryAddress;
use App\Models\User;
use App\Services\Profile\DeliveryAddressService;

class UpdateDeliveryAddressAction
{
    public function __construct(
        private readonly DeliveryAddressService $deliveryAddressService,
    ) {}

    public function handle(User $user, UpdateDeliveryAddressRequest $request): DeliveryAddress
    {
        return $this->deliveryAddressService->update($user, $request->validated());
    }
}
