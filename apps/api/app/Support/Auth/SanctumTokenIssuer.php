<?php

namespace App\Support\Auth;

use App\Models\Admin;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\NewAccessToken;

/**
 * RC1-G4A — Issue Sanctum PATs with finite, role-specific expiry.
 */
final class SanctumTokenIssuer
{
    public static function issueAdmin(Admin $admin, string $name = 'admin-api'): NewAccessToken
    {
        return $admin->createToken($name, ['*'], self::expiresAt('admin'));
    }

    public static function issueCustomer(User $user, string $name = 'customer-api'): NewAccessToken
    {
        return $user->createToken($name, ['*'], self::expiresAt('customer'));
    }

    public static function adminExpirationMinutes(): int
    {
        return max(1, (int) config('sanctum.admin_expiration_minutes', 480));
    }

    public static function customerExpirationMinutes(): int
    {
        return max(1, (int) config('sanctum.customer_expiration_minutes', 10080));
    }

    public static function revokeAll(Authenticatable $actor): void
    {
        if (! method_exists($actor, 'tokens')) {
            return;
        }

        $actor->tokens()->delete();
    }

    private static function expiresAt(string $audience): DateTimeInterface
    {
        $minutes = $audience === 'admin'
            ? self::adminExpirationMinutes()
            : self::customerExpirationMinutes();

        return now()->addMinutes($minutes);
    }
}
