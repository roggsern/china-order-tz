<?php

namespace App\Services\Notifications\WhatsApp;

use App\Models\Order;
use App\Services\Stores\StoreSettingsResolver;

/**
 * Resolves a customer-facing pickup location from existing store business settings.
 * Does not invent warehouse street addresses that the domain does not store.
 */
final class PickupLocationResolver
{
    public function __construct(
        private readonly StoreSettingsResolver $storeSettings,
    ) {}

    public function forOrder(?Order $order): ?string
    {
        if ($order === null) {
            return null;
        }

        $order->loadMissing('store');
        $store = $order->store;
        if ($store === null) {
            return null;
        }

        $address = trim((string) ($this->storeSettings->resolveSections($store)['business']['address'] ?? ''));

        return $address !== '' ? $address : null;
    }
}
