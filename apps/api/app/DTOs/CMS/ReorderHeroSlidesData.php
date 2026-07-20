<?php

namespace App\DTOs\CMS;

final class ReorderHeroSlidesData
{
    /**
     * Complete unique ordered set of hero slide IDs for one HERO section.
     *
     * @param  list<string>  $slideIds
     */
    public function __construct(
        public readonly array $slideIds,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $ids = $payload['slide_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        return new self(
            slideIds: array_values(array_map(static fn ($id) => (string) $id, $ids)),
        );
    }
}
