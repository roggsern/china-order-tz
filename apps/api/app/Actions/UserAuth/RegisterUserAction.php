<?php

namespace App\Actions\UserAuth;

use App\Enums\CustomerRegistrationSource;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Crm\CustomerProfileService;
use App\Support\Auth\SanctumTokenIssuer;
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
        $nameParts = $this->resolveRegistrationNameParts($request);

        $user = User::query()->create([
            'name' => $nameParts['name'],
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
            'email_verified_at' => null,
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

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning('auth.email_verification_on_register_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        $token = SanctumTokenIssuer::issueCustomer($user)->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Prefer explicit first/last from the client; otherwise split the display name.
     * Never derives identity from delivery/address payloads.
     *
     * @return array{name: string, first_name: string|null, last_name: string|null}
     */
    private function resolveRegistrationNameParts(RegisterRequest $request): array
    {
        $validated = $request->validated();
        $fullName = trim((string) ($validated['name'] ?? ''));
        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));

        if ($firstName === '' && $lastName === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, 2) ?: [];
            $firstName = trim((string) ($parts[0] ?? ''));
            $lastName = trim((string) ($parts[1] ?? ''));
        }

        if ($fullName === '') {
            $fullName = trim("{$firstName} {$lastName}");
        }

        return [
            'name' => $fullName,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
        ];
    }
}
