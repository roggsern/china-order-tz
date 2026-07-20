<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsFeaturedDisplayStyle;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsStatus;

final class UpdateFeaturedContentData
{
    /**
     * @param  array<string, mixed>|null  $configuration
     * @param  array<string, true>  $present
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $subtitle = null,
        public readonly ?CmsFeaturedSourceType $sourceType = null,
        public readonly ?int $limit = null,
        public readonly ?string $sortOrder = null,
        public readonly ?CmsFeaturedDisplayStyle $displayStyle = null,
        public readonly ?array $configuration = null,
        public readonly ?int $position = null,
        public readonly ?CmsStatus $status = null,
        public readonly ?bool $isVisible = null,
        public readonly array $present = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $keys = [
            'title', 'subtitle', 'source_type', 'limit', 'sort_order',
            'display_style', 'configuration', 'position', 'status', 'is_visible',
        ];
        $present = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $present[$key] = true;
            }
        }

        $configuration = null;
        if (array_key_exists('configuration', $payload)) {
            $configuration = is_array($payload['configuration']) ? $payload['configuration'] : [];
        }

        return new self(
            title: array_key_exists('title', $payload) ? trim((string) $payload['title']) : null,
            subtitle: array_key_exists('subtitle', $payload)
                ? ($payload['subtitle'] !== null ? trim((string) $payload['subtitle']) : null)
                : null,
            sourceType: array_key_exists('source_type', $payload)
                ? CmsFeaturedSourceType::from((string) $payload['source_type'])
                : null,
            limit: array_key_exists('limit', $payload) ? (int) $payload['limit'] : null,
            sortOrder: array_key_exists('sort_order', $payload) ? (string) $payload['sort_order'] : null,
            displayStyle: array_key_exists('display_style', $payload)
                ? CmsFeaturedDisplayStyle::from((string) $payload['display_style'])
                : null,
            configuration: $configuration,
            position: array_key_exists('position', $payload) ? (int) $payload['position'] : null,
            status: array_key_exists('status', $payload)
                ? CmsStatus::from((string) $payload['status'])
                : null,
            isVisible: array_key_exists('is_visible', $payload) ? (bool) $payload['is_visible'] : null,
            present: $present,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->present[$key]);
    }
}
