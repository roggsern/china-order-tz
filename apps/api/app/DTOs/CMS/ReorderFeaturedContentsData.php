<?php

namespace App\DTOs\CMS;

final class ReorderFeaturedContentsData
{
    /**
     * @param  list<string>  $featuredContentIds
     */
    public function __construct(
        public readonly array $featuredContentIds,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $ids = $payload['featured_content_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        return new self(
            featuredContentIds: array_values(array_map(static fn ($id) => (string) $id, $ids)),
        );
    }
}
