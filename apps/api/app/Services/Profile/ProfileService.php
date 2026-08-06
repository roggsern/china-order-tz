<?php

namespace App\Services\Profile;

use App\Models\User;

class ProfileService
{
    public function show(User $user): User
    {
        return $user;
    }

    /**
     * Email is not mutable here — use CustomerEmailChangeService.
     *
     * Name fields update only when both first_name and last_name are provided.
     * Phone-only patches leave identity names untouched (checkout phone sync).
     *
     * @param  array{
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     phone?: string|null
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        $payload = [];

        $hasFirst = array_key_exists('first_name', $data) && filled($data['first_name']);
        $hasLast = array_key_exists('last_name', $data) && filled($data['last_name']);

        if ($hasFirst && $hasLast) {
            $firstName = trim((string) $data['first_name']);
            $lastName = trim((string) $data['last_name']);
            $payload['first_name'] = $firstName;
            $payload['last_name'] = $lastName;
            $payload['name'] = trim("{$firstName} {$lastName}");
        }

        if (array_key_exists('phone', $data)) {
            $payload['phone'] = $data['phone'];
        }

        if ($payload !== []) {
            $user->update($payload);
        }

        return $user->fresh() ?? $user;
    }
}
