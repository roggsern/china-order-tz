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
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     phone?: string|null,
     *     email: string
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'phone' => $data['phone'] ?? $user->phone,
            'email' => $data['email'],
        ]);

        return $user->fresh();
    }
}
