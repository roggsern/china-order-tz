<?php

namespace App\Services\Auth;

use App\Enums\NotificationEventType;
use App\Events\Audit\CustomerPasswordResetCompletedAudit;
use App\Events\Audit\CustomerPasswordResetRequestedAudit;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Customer password recovery via Laravel password broker + NotificationPlatform.
 * Never reveals whether an email exists. Never stores plaintext tokens outside the broker hash table.
 */
final class CustomerPasswordResetService
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * Always returns the same safe acknowledgement payload.
     *
     * @return array{success: bool, message: string}
     */
    public function requestReset(string $email): array
    {
        $safe = [
            'success' => true,
            'message' => 'If an account exists for that email, password reset instructions have been sent.',
        ];

        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return $safe;
        }

        $user = User::query()
            ->where('email', $normalized)
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return $safe;
        }

        $broker = $this->broker();
        $tokens = $broker->getRepository();

        // Respect broker throttle without revealing account existence.
        if ($tokens->recentlyCreatedToken($user)) {
            return $safe;
        }

        $token = $tokens->create($user);
        $this->dispatchResetNotification($user, $token);
        event(CustomerPasswordResetRequestedAudit::forUser($user));

        return $safe;
    }

    /**
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $payload
     * @return array{success: bool, message: string}
     */
    public function resetPassword(array $payload): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        $status = $this->broker()->reset(
            [
                'email' => $email,
                'token' => (string) ($payload['token'] ?? ''),
                'password' => (string) ($payload['password'] ?? ''),
                'password_confirmation' => (string) ($payload['password_confirmation'] ?? ''),
            ],
            function (User $user, string $password): void {
                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        'email' => ['This password reset link is invalid or has expired.'],
                    ]);
                }

                $user->forceFill([
                    'password' => $password,
                ])->save();

                // Sanctum tokens are revoked by User::booted on password change.
                event(CustomerPasswordResetCompletedAudit::forUser($user->fresh() ?? $user));
            },
        );

        return match ($status) {
            Password::PASSWORD_RESET => [
                'success' => true,
                'message' => 'Your password has been reset. You can sign in with your new password.',
            ],
            Password::RESET_THROTTLED => throw ValidationException::withMessages([
                'email' => ['Please wait before retrying.'],
            ]),
            default => throw ValidationException::withMessages([
                'email' => ['This password reset link is invalid or has expired.'],
            ]),
        };
    }

    private function dispatchResetNotification(User $user, string $token): void
    {
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $resetUrl = $frontend.'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);

        $this->notifications->notifyCustomer(
            NotificationEventType::PasswordReset,
            $user,
            [
                'customer_name' => $user->name ?: ($user->first_name ?: 'Customer'),
                'reset_code' => $token,
                'reset_url' => $resetUrl,
                'expires_minutes' => (int) config('auth.passwords.users.expire', 60),
                'idempotency_key' => 'password_reset:'.$user->id.':'.sha1($token),
            ],
            title: 'Reset your password',
        );
    }

    private function broker(): PasswordBroker
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker('users');

        return $broker;
    }
}
