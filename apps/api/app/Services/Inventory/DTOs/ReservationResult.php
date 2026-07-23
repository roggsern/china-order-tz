<?php

namespace App\Services\Inventory\DTOs;

/**
 * Reservation operation result (ADR 055 / Phase 2A-3B-4).
 */
final class ReservationResult
{
    /**
     * @param  list<InventoryMutationResult>  $lineResults
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $operation,
        public readonly int $linesAffected,
        public readonly int $linesIdempotent,
        public readonly array $lineResults = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'operation' => $this->operation,
            'lines_affected' => $this->linesAffected,
            'lines_idempotent' => $this->linesIdempotent,
            'line_results' => array_map(
                static fn (InventoryMutationResult $r) => $r->toArray(),
                $this->lineResults,
            ),
            'meta' => $this->meta,
        ];
    }
}
