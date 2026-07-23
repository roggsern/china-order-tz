<?php

namespace App\Services\Inventory\DTOs;

use App\Models\Order;

/**
 * Result of committing inventory for a paid order (ADR 055).
 */
final class InventoryCommitmentResult
{
    /**
     * @param  list<InventoryMutationResult>  $itemResults
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $committed,
        public readonly Order $order,
        public readonly int $itemsCommitted,
        public readonly int $itemsSkippedIdempotent,
        public readonly array $itemResults = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'committed' => $this->committed,
            'order_id' => $this->order->id,
            'items_committed' => $this->itemsCommitted,
            'items_skipped_idempotent' => $this->itemsSkippedIdempotent,
            'item_results' => array_map(
                static fn (InventoryMutationResult $r) => $r->toArray(),
                $this->itemResults,
            ),
            'meta' => $this->meta,
        ];
    }
}
