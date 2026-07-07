<?php

namespace App\Services\Profile;

use App\Models\DeliveryAddress;
use App\Models\User;

class DeliveryAddressService
{
    public function show(User $user): DeliveryAddress
    {
        $address = $user->deliveryAddress;

        if ($address === null) {
            abort(404);
        }

        return $address;
    }

    /**
     * @param  array{
     *     recipient_name: string,
     *     phone: string,
     *     country: string,
     *     region: string,
     *     city: string,
     *     district: string,
     *     street: string,
     *     landmark?: string|null,
     *     postal_code?: string|null
     * }  $data
     */
    public function update(User $user, array $data): DeliveryAddress
    {
        return DeliveryAddress::query()->updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );
    }
}
