<?php

namespace App\Events\CustomerAgent;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** @internal Idempotent domain signal — listeners must tolerate duplicates via history keys. */
abstract class CustomerAgentDomainEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $pickupId,
        public readonly string $orderId,
        public readonly ?string $adminId = null,
    ) {}
}
