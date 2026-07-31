<?php

namespace App\Services\Auth;

use App\Enums\NotificationEventType;
use App\Events\Audit\CustomerEmailVerificationRequestedAudit;
use App\Events\Audit\CustomerEmailVerifiedAudit;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Laravel-compatible customer email verification via signed URLs + NotificationPlatform.
 * Does not gate checkout / commerce (no verified middleware on those routes).
 */
final class CustomerEmailVerificationService
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * @return array{success: bool, message: string, already_verified: bool}
     */
    public function send(User $user): array
    {
        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'message' => 'Your email is already verified.',
                'already_verified' => true,
            ];
        }

        $verifyUrl = $this->frontendVerificationUrl($user);

        $this->notifications->notifyCustomer(
            NotificationEventType::EmailVerificationRequested,
            $user,
            [
                'customer_name' => $user->name ?: ($user->first_name ?: 'Customer'),
                'email' => $user->email,
                'verify_url' => $verifyUrl,
                'expires_minutes' => (int) Config::get('auth.verification.expire', 60),
                'idempotency_key' => 'email_verification_requested:'.$user->id.':'.now()->format('YmdHi'),
            ],
            title: 'Verify your email address',
        );

        event(CustomerEmailVerificationRequestedAudit::forUser($user));

        return [
            'success' => true,
            'message' => 'A verification link has been sent to your email address.',
            'already_verified' => false,
        ];
    }

    /**
     * @return array{success: bool, message: string, already_verified: bool}
     */
    public function verify(User $user, string $hash): array
    {
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw ValidationException::withMessages([
                'hash' => ['This verification link is invalid or has expired.'],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'message' => 'Your email is already verified.',
                'already_verified' => true,
            ];
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            event(CustomerEmailVerifiedAudit::forUser($user->fresh() ?? $user));
            $this->dispatchVerifiedNotification($user->fresh() ?? $user);
        }

        return [
            'success' => true,
            'message' => 'Your email address has been verified.',
            'already_verified' => false,
        ];
    }

    public function frontendVerificationUrl(User $user): string
    {
        $minutes = (int) Config::get('auth.verification.expire', 60);

        $signedApiUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($minutes),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $parts = parse_url($signedApiUrl);
        parse_str($parts['query'] ?? '', $query);

        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

        return $frontend.'/verify-email?'.http_build_query([
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
            'expires' => $query['expires'] ?? null,
            'signature' => $query['signature'] ?? null,
        ]);
    }

    private function dispatchVerifiedNotification(User $user): void
    {
        $this->notifications->notifyCustomer(
            NotificationEventType::EmailVerified,
            $user,
            [
                'customer_name' => $user->name ?: ($user->first_name ?: 'Customer'),
                'email' => $user->email,
                'idempotency_key' => 'email_verified:'.$user->id.':'.now()->timestamp,
            ],
            title: 'Email verified',
        );
    }
}
