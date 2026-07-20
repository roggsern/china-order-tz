<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsFeaturedDisplayStyle;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsStatus;

final class CreateFeaturedContentData
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly CmsFeaturedSourceType $sourceType,
        public readonly int $limit,
        public readonly string $sortOrder,
        public readonly CmsFeaturedDisplayStyle $displayStyle,
        public readonly array $configuration,
        public readonly int $position,
        public readonly CmsStatus $status,
        public readonly bool $isVisible,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $config = $payload['configuration'] ?? [];
        if (! is_array($config)) {
            $config = [];
        }

        return new self(
            title: trim((string) $payload['title']),
            subtitle: isset($payload['subtitle']) && $payload['subtitle'] !== null
                ? trim((string) $payload['subtitle'])
                : null,
            sourceType: CmsFeaturedSourceType::from((string) $payload['source_type']),
            limit: (int) ($payload['limit'] ?? 8),
            sortOrder: (string) ($payload['sort_order'] ?? 'default'),
            displayStyle: isset($payload['display_style'])
                ? CmsFeaturedDisplayStyle::from((string) $payload['display_style'])
                : CmsFeaturedDisplayStyle::Grid,
            configuration: $config,
            position: (int) ($payload['position'] ?? 0),
            status: isset($payload['status'])
                ? CmsStatus::from((string) $payload['status'])
                : CmsStatus::Draft,
            isVisible: (bool) ($payload['is_visible'] ?? true),
        );
    }
}
