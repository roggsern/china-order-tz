<?php

namespace App\Services\Pricing\DTOs;

final class PriceStageResult
{
    public function __construct(
        public readonly string $stage,
        public readonly string $label,
        public readonly string $unitPrice,
        public readonly bool $applied,
        public readonly ?string $note = null,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'label' => $this->label,
            'unit_price' => $this->unitPrice,
            'applied' => $this->applied,
            'note' => $this->note,
            'meta' => $this->meta === [] ? (object) [] : $this->meta,
        ];
    }
}
