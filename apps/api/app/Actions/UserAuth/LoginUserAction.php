<?php

namespace App\Actions\UserAuth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\Auth\SanctumTokenIssuer;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function handle(LoginRequest $request): array
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials)) {
            $exception = ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);

            // Keep message-centric body for web; add Contract v1 code + errors (additive).
            $exception->response = ApiResponse::error(
                message: 'Invalid credentials',
                code: 'invalid_credentials',
                status: 422,
                extra: [
                    'errors' => [
                        'email' => ['Invalid credentials'],
                    ],
                ],
            );

            throw $exception;
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw new HttpResponseException(ApiResponse::error(
                message: 'Your account has been disabled.',
                code: 'account_disabled',
                status: 403,
            ));
        }

        // Customers keep multi-device tokens; new token still has finite expiry.
        $token = SanctumTokenIssuer::issueCustomer($user)->plainTextToken;

        Auth::guard('web')->logout();

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }
}
