<?php

namespace App\DTOs\CMS;

final class ReorderCmsNavigationItemsData
{
    /**
     * @param  list<array{id: string, position: int, parent_id?: string|null}>  $items
     */
    public function __construct(public readonly array $items) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $items = [];
        foreach ($payload['items'] ?? [] as $row) {
            $items[] = [
                'id' => (string) $row['id'],
                'position' => (int) $row['position'],
                'parent_id' => array_key_exists('parent_id', $row)
                    ? ($row['parent_id'] !== null && $row['parent_id'] !== '' ? (string) $row['parent_id'] : null)
                    : null,
                'has_parent' => array_key_exists('parent_id', $row),
            ];
        }

        return new self($items);
    }
}
