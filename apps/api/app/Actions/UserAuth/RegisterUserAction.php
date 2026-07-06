<?php

namespace App\Actions\UserAuth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;

class RegisterUserAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function handle(RegisterRequest $request): array
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
        ]);

        $customerRole = Role::query()->where('slug', 'customer')->firstOrFail();
        $user->roles()->attach($customerRole->id);

        $token = $user->createToken('customer-api')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }
}
