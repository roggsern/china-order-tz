<?php

namespace App\Services\Profile;

use App\Models\DeliveryAddress;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Customer saved-address book (user_addresses).
 * Checkout continues to use DeliveryAddress as the order-time profile copy;
 * selecting a saved address syncs into that singleton before order snapshotting.
 */
final class CustomerAddressService
{
    /**
     * @return Collection<int, UserAddress>
     */
    public function listFor(User $user): Collection
    {
        return UserAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findOwned(User $user, string $addressId): UserAddress
    {
        $address = UserAddress::query()
            ->where('user_id', $user->id)
            ->where('id', $addressId)
            ->first();

        if ($address === null) {
            throw new NotFoundHttpException('Address not found.');
        }

        return $address;
    }

    public function defaultFor(User $user): ?UserAddress
    {
        return UserAddress::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first()
            ?? UserAddress::query()
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): UserAddress
    {
        return DB::transaction(function () use ($user, $data) {
            $payload = $this->normalizePayload($data);
            $makeDefault = (bool) ($payload['is_default'] ?? false)
                || ! UserAddress::query()->where('user_id', $user->id)->exists();

            if ($makeDefault) {
                $this->clearDefaults($user);
            }

            $created = UserAddress::query()->create([
                'user_id' => $user->id,
                'label' => $payload['label'] ?? null,
                'recipient_name' => $payload['recipient_name'],
                'phone' => $payload['phone'],
                'address_line_1' => $payload['street'],
                'address_line_2' => $payload['district'],
                'city' => $payload['city'],
                'region' => $payload['region'],
                'postal_code' => $payload['postal_code'] ?? null,
                'country' => $payload['country'],
                'is_shipping' => (bool) ($payload['is_shipping'] ?? true),
                'is_billing' => (bool) ($payload['is_billing'] ?? false),
                'is_default' => $makeDefault,
            ]);

            if ($makeDefault) {
                $this->syncToDeliveryAddress($user, $created);
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, UserAddress $address, array $data): UserAddress
    {
        $this->assertOwned($user, $address);

        return DB::transaction(function () use ($user, $address, $data) {
            $payload = $this->normalizePayload($data, partial: true);

            if (array_key_exists('street', $payload)) {
                $address->address_line_1 = $payload['street'];
            }
            if (array_key_exists('district', $payload)) {
                $address->address_line_2 = $payload['district'];
            }
            foreach (['label', 'recipient_name', 'phone', 'city', 'region', 'postal_code', 'country', 'is_shipping', 'is_billing'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $address->{$field} = $payload[$field];
                }
            }

            if (array_key_exists('is_default', $payload) && $payload['is_default']) {
                $this->clearDefaults($user);
                $address->is_default = true;
            }

            $address->save();

            return $address->fresh() ?? $address;
        });
    }

    public function delete(User $user, UserAddress $address): void
    {
        $this->assertOwned($user, $address);

        DB::transaction(function () use ($user, $address) {
            $wasDefault = (bool) $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $next = UserAddress::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('updated_at')
                    ->first();
                if ($next !== null) {
                    $next->is_default = true;
                    $next->save();
                }
            }
        });
    }

    public function setDefault(User $user, UserAddress $address): UserAddress
    {
        $this->assertOwned($user, $address);

        return DB::transaction(function () use ($user, $address) {
            $this->clearDefaults($user);
            $address->is_default = true;
            $address->save();

            $fresh = $address->fresh() ?? $address;
            $this->syncToDeliveryAddress($user, $fresh);

            return $fresh;
        });
    }

    /**
     * Copy a saved address into the checkout DeliveryAddress singleton.
     * Order snapshots continue to read DeliveryAddress / order shipping snapshot — unchanged.
     */
    public function syncToDeliveryAddress(User $user, UserAddress $address): DeliveryAddress
    {
        $this->assertOwned($user, $address);

        return DeliveryAddress::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'country' => $address->country ?: 'Tanzania',
                'region' => (string) $address->region,
                'city' => $address->city,
                'district' => (string) ($address->address_line_2 ?: $address->city),
                'street' => $address->address_line_1,
                'landmark' => null,
                'postal_code' => $address->postal_code,
            ],
        );
    }

    /**
     * Preload default saved address into DeliveryAddress when none exists yet.
     */
    public function ensureDeliveryAddressFromDefault(User $user): ?DeliveryAddress
    {
        $existing = DeliveryAddress::query()->where('user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $default = $this->defaultFor($user);
        if ($default === null) {
            return null;
        }

        return $this->syncToDeliveryAddress($user, $default);
    }

    private function assertOwned(User $user, UserAddress $address): void
    {
        if ($address->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'address' => ['You do not own this address.'],
            ]);
        }
    }

    private function clearDefaults(User $user): void
    {
        UserAddress::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, bool $partial = false): array
    {
        $out = $data;

        if (isset($data['street'])) {
            $out['street'] = trim((string) $data['street']);
        } elseif (isset($data['address_line_1'])) {
            $out['street'] = trim((string) $data['address_line_1']);
        } elseif (! $partial) {
            $out['street'] = '';
        }

        if (isset($data['district'])) {
            $out['district'] = trim((string) $data['district']);
        } elseif (isset($data['address_line_2'])) {
            $out['district'] = trim((string) $data['address_line_2']);
        } elseif (! $partial) {
            $out['district'] = '';
        }

        foreach (['recipient_name', 'phone', 'city', 'region', 'country', 'label', 'postal_code'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $out[$field] = trim($data[$field]);
            }
        }

        if (! $partial && empty($out['country'])) {
            $out['country'] = 'Tanzania';
        }

        return $out;
    }
}
