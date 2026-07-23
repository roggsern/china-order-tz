<?php

namespace App\Services\Inventory\DTOs;

use App\Models\Admin;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Collection;

/**
 * Context for InventoryCommitmentService (ADR 055 / Phase 2A-3B-3).
 *
 * Reservation conversion / warehouse allocation are reserved placeholders.
 */
final class InventoryCommitmentContext
{
    /**
     * @param  Collection<int, \App\Models\OrderItem>|null  $orderItems
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly Order $order,
        public readonly ?Collection $orderItems = null,
        public readonly ?PaymentTransaction $payment = null,
        public readonly ?Admin $actor = null,
        public readonly ?string $channel = null,
        public readonly string $source = 'payment',
        public readonly array $metadata = [],
        /** Reserved — applied when order has convertible checkout holds (2A-3C-2). */
        public readonly bool $fromReservation = false,
        /** Reserved — not applied in 2A-3B-3. */
        public readonly ?string $warehouseCode = null,
        /**
         * When false, insufficient stock on a not-yet-committed line logs and skips
         * instead of failing (used for already-paid payment retries).
         */
        public readonly bool $strict = true,
    ) {}
}
