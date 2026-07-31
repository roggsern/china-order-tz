<?php

namespace App\Services\Auth;

use App\Enums\NotificationEventType;
use App\Events\Audit\CustomerPasswordChangedAudit;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated customer password change.
 *
 * Device behavior: updating the password triggers User::booted, which deletes
 * ALL Sanctum personal access tokens for the customer (including the current
 * device). The client must sign in again after a successful change.
 */
final class CustomerChangePasswordService
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * @param  array{current_password: string, password: string, password_confirmation: string}  $payload
     * @return array{success: bool, message: string, requires_reauthentication: bool}
     */
    public function change(User $user, array $payload): array
    {
        if (! Hash::check((string) $payload['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $newPassword = (string) $payload['password'];

        if (Hash::check($newPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from your current password.'],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->save();

        // All Sanctum tokens (including current device) are revoked by User::booted.
        $fresh = $user->fresh() ?? $user;

        event(CustomerPasswordChangedAudit::forUser($fresh));
        $this->dispatchPasswordChangedNotification($fresh);

        return [
            'success' => true,
            'message' => 'Your password has been changed. Please sign in again.',
            'requires_reauthentication' => true,
        ];
    }

    private function dispatchPasswordChangedNotification(User $user): void
    {
        $this->notifications->notifyCustomer(
            NotificationEventType::PasswordChanged,
            $user,
            [
                'customer_name' => $user->name ?: ($user->first_name ?: 'Customer'),
                'changed_at' => now()->toIso8601String(),
                'idempotency_key' => 'password_changed:'.$user->id.':'.now()->timestamp,
            ],
            title: 'Your password was changed',
        );
    }
}
