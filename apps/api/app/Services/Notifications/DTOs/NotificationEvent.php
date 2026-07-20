<?php

namespace App\Services\Notifications\DTOs;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;

/**
 * Business modules publish this DTO only — never call channel providers.
 */
final class NotificationEvent
{
    /**
     * @param  array<string, mixed>  $data  Template variables + context
     * @param  list<NotificationChannel>|null  $channels  Optional channel override
     */
    public function __construct(
        public readonly NotificationEventType $type,
        public readonly array $data = [],
        public readonly ?string $customerId = null,
        public readonly ?string $adminId = null,
        public readonly ?array $channels = null,
        public readonly ?string $title = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $correlationKey = null,
    ) {}
}
