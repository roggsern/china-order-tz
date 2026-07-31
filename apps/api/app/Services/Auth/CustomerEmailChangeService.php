<?php

namespace App\Services\Auth;

use App\Enums\NotificationEventType;
use App\Events\Audit\CustomerEmailChangeRequestedAudit;
use App\Events\Audit\CustomerEmailChangedAudit;
use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Secure pending-email change. users.email is only updated after token confirmation.
 */
final class CustomerEmailChangeService
{
    private const EXPIRE_MINUTES = 60;

    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     data: array{pending_email: string, expires_at: string|null}
     * }
     */
    public function request(User $user, string $newEmail, string $currentPassword): array
    {
        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $normalized = strtolower(trim($newEmail));
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'new_email' => ['Please enter a valid email address.'],
            ]);
        }

        if ($normalized === strtolower((string) $user->email)) {
            throw ValidationException::withMessages([
                'new_email' => ['New email must be different from your current email.'],
            ]);
        }

        $taken = User::query()
            ->where('email', $normalized)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'new_email' => ['This email address is already in use.'],
            ]);
        }

        $plainToken = Str::random(64);

        $request = DB::transaction(function () use ($user, $normalized, $plainToken) {
            EmailChangeRequest::query()
                ->where('user_id', $user->id)
                ->whereNull('confirmed_at')
                ->delete();

            return EmailChangeRequest::query()->create([
                'user_id' => $user->id,
                'old_email' => $user->email,
                'new_email' => $normalized,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes(self::EXPIRE_MINUTES),
            ]);
        });

        event(CustomerEmailChangeRequestedAudit::forRequest($user, $request));
        $this->dispatchRequestedNotifications($user, $request, $plainToken);

        return [
            'success' => true,
            'message' => 'We sent a confirmation link to your new email address.',
            'data' => [
                'pending_email' => $request->new_email,
                'expires_at' => $request->expires_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{success: bool, message: string, data: array{email: string}}
     */
    public function confirm(string $plainToken): array
    {
        $token = trim($plainToken);
        if ($token === '') {
            throw ValidationException::withMessages([
                'token' => ['This confirmation link is invalid or has expired.'],
            ]);
        }

        $match = EmailChangeRequest::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('confirmed_at')
            ->first();

        if ($match === null || $match->expires_at === null || $match->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This confirmation link is invalid or has expired.'],
            ]);
        }

        $user = User::query()->find($match->user_id);
        if ($user === null || ! $user->is_active) {
            throw ValidationException::withMessages([
                'token' => ['This confirmation link is invalid or has expired.'],
            ]);
        }

        $taken = User::query()
            ->where('email', $match->new_email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'token' => ['This email address is no longer available.'],
            ]);
        }

        DB::transaction(function () use ($user, $match) {
            $user->forceFill([
                'email' => $match->new_email,
                'email_verified_at' => now(),
            ])->save();

            $match->forceFill([
                'confirmed_at' => now(),
            ])->save();

            EmailChangeRequest::query()
                ->where('user_id', $user->id)
                ->whereNull('confirmed_at')
                ->where('id', '!=', $match->id)
                ->delete();
        });

        $fresh = $user->fresh() ?? $user;
        event(CustomerEmailChangedAudit::forUser($fresh, $match->old_email, $match->new_email));
        $this->dispatchChangedNotification($fresh, $match->old_email);

        return [
            'success' => true,
            'message' => 'Your email address has been updated.',
            'data' => [
                'email' => $fresh->email,
            ],
        ];
    }

    public function pendingFor(User $user): ?EmailChangeRequest
    {
        return EmailChangeRequest::query()
            ->where('user_id', $user->id)
            ->whereNull('confirmed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();
    }

    private function dispatchRequestedNotifications(User $user, EmailChangeRequest $request, string $plainToken): void
    {
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $confirmUrl = $frontend.'/account/security?'.http_build_query([
            'email_change_token' => $plainToken,
        ]);

        // In-app + email (when configured) for the account; confirm link targets the new address in copy.
        $this->notifications->notifyCustomer(
            NotificationEventType::EmailChangeRequested,
            $user,
            [
                'customer_name' => $user->name ?: ($user->first_name ?: 'Customer'),
                'old_email' => $request->old_email,
                'new_email' => $request->new_email,
                'confirm_url' => $confirmUrl,
                'expires_minutes' => self::EXPIRE_MINUTES,
                'idempotency_key' => 'email_change_requested:'.$request->id,
            ],
            title: 'Confirm your new email',
        );
    }

    private function dispatchChangedNotification(User $user, string $oldEmail): void
    {
        $this->notifications->notifyCustomer(
            NotificationEventType::EmailChanged,
            $user,
            [
                'customer_name' => $user->name ?: ($user->first_name ?: 'Customer'),
                'old_email' => $oldEmail,
                'new_email' => $user->email,
                'idempotency_key' => 'email_changed:'.$user->id.':'.now()->timestamp,
            ],
            title: 'Your email was updated',
        );
    }
}
