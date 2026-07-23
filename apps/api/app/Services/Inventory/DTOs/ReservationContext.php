<?php

namespace App\Services\Inventory\DTOs;

use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Reservation request context (ADR 055 / Phase 2A-3B-4).
 */
final class ReservationContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ?CheckoutSession $checkoutSession = null,
        public readonly ?Cart $cart = null,
        public readonly ?Order $order = null,
        public readonly ?string $productId = null,
        public readonly ?string $productVariantId = null,
        public readonly int $quantity = 1,
        public readonly ?CarbonInterface $expiresAt = null,
        public readonly string $source = 'checkout',
        public readonly array $metadata = [],
        /** Reserved placeholder — convert invoked via convertToCommit(). */
        public readonly bool $convertToCommitment = false,
    ) {}
}
