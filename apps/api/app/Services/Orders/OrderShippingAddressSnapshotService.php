<?php

namespace App\Services\Orders;

use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Services\Profile\CustomerAddressService;

/**
 * Persists immutable checkout delivery address snapshots onto shipping_addresses.
 */
class OrderShippingAddressSnapshotService
{
    public function __construct(
        private readonly CustomerAddressService $customerAddresses,
    ) {}

    public function ensureFromDeliveryAddress(Order $order, User $user): ?ShippingAddress
    {
        $order->loadMissing('shippingAddress');
        if ($order->shippingAddress !== null) {
            return $order->shippingAddress;
        }

        $deliveryAddress = $this->resolveDeliveryAddress($user);
        if ($deliveryAddress === null) {
            return null;
        }

        $snapshot = $this->createFromDeliveryAddress($order, $user, $deliveryAddress);
        $order->setRelation('shippingAddress', $snapshot);

        return $snapshot;
    }

    private function resolveDeliveryAddress(User $user): ?DeliveryAddress
    {
        $user->unsetRelation('deliveryAddress');
        $user->load('deliveryAddress');

        if ($user->deliveryAddress !== null) {
            return $user->deliveryAddress;
        }

        // Checkout may only populate user_addresses; sync default saved address before snapshotting.
        $synced = $this->customerAddresses->ensureDeliveryAddressFromDefault($user);
        if ($synced === null) {
            return null;
        }

        $user->setRelation('deliveryAddress', $synced);

        return $synced;
    }

    public function createFromDeliveryAddress(
        Order $order,
        User $user,
        DeliveryAddress $deliveryAddress,
    ): ShippingAddress {
        [$firstName, $lastName] = $this->splitRecipientName((string) $deliveryAddress->recipient_name);

        return ShippingAddress::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $deliveryAddress->phone,
            'email' => $user->email,
            'address_line_1' => $deliveryAddress->street,
            'address_line_2' => $this->buildAddressLine2(
                $deliveryAddress->district,
                $deliveryAddress->landmark,
            ),
            'city' => $deliveryAddress->city,
            'region' => $deliveryAddress->region,
            'postal_code' => $deliveryAddress->postal_code,
            'country' => $deliveryAddress->country ?: 'Tanzania',
            'is_default' => false,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRecipientName(string $recipientName): array
    {
        $trimmed = trim($recipientName);
        if ($trimmed === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $trimmed, 2) ?: [];

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    private function buildAddressLine2(?string $district, ?string $landmark): ?string
    {
        $districtValue = trim((string) ($district ?? ''));
        $landmarkValue = trim((string) ($landmark ?? ''));

        if ($districtValue !== '' && $landmarkValue !== '') {
            return "{$districtValue} · {$landmarkValue}";
        }

        if ($districtValue !== '') {
            return $districtValue;
        }

        if ($landmarkValue !== '') {
            return $landmarkValue;
        }

        return null;
    }
}
