<?php

namespace App\Services\Orders\Lifecycle;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\User;

/**
 * Context for an authoritative order lifecycle transition.
 */
final class OrderLifecycleContext
{
    public function __construct(
        public readonly string $source,
        public readonly ?string $reason = null,
        public readonly ?Admin $admin = null,
        public readonly ?User $user = null,
        public readonly ?string $idempotencyKey = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    public static function system(string $source, ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): self
    {
        return new self(
            source: $source,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );
    }

    public static function payment(string $reason = 'Payment verified', ?string $idempotencyKey = null, array $metadata = []): self
    {
        return self::system('payment', $reason, $idempotencyKey, $metadata);
    }

    public static function admin(Admin $admin, string $source, ?string $reason = null, array $metadata = []): self
    {
        return new self(
            source: $source,
            reason: $reason,
            admin: $admin,
            metadata: $metadata,
        );
    }

    public static function customer(User $user, string $source, ?string $reason = null, array $metadata = []): self
    {
        return new self(
            source: $source,
            reason: $reason,
            user: $user,
            metadata: $metadata,
        );
    }

    public static function fulfillment(string $reason, array $metadata = []): self
    {
        return self::system('fulfillment', $reason, null, $metadata);
    }
}
