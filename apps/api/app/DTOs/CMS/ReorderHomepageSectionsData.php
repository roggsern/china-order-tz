<?php

namespace App\DTOs\CMS;

final class ReorderHomepageSectionsData
{
    /**
     * Ordered section UUIDs for a single layout. Must be a complete, unique set
     * of that layout's section IDs (no extras, no omissions, no duplicates).
     *
     * @param  list<string>  $sectionIds
     */
    public function __construct(
        public readonly array $sectionIds,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $ids = $payload['section_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        return new self(
            sectionIds: array_values(array_map(static fn ($id) => (string) $id, $ids)),
        );
    }
}
