<?php

namespace App\Actions\UserAuth;

use App\Enums\CustomerRegistrationSource;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Crm\CustomerProfileService;
use Illuminate\Support\Facades\Log;

class RegisterUserAction
{
    public function __construct(
        private readonly CustomerProfileService $customerProfiles,
    ) {}

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

        $source = CustomerRegistrationSource::tryFrom((string) $request->input('registration_source', ''))
            ?? CustomerRegistrationSource::SelfRegistration;

        try {
            $this->customerProfiles->ensureForUser($user, $source);
        } catch (\Throwable $e) {
            Log::warning('crm.profile_on_register_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        $token = $user->createToken('customer-api')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }
}
