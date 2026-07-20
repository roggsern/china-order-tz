<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsHomepageSectionType;

final class CreateHomepageSectionData
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public readonly CmsHomepageSectionType $sectionType,
        public readonly ?string $title,
        public readonly ?string $subtitle,
        public readonly int $position,
        public readonly bool $isVisible,
        public readonly array $configuration,
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
            sectionType: CmsHomepageSectionType::from((string) $payload['section_type']),
            title: array_key_exists('title', $payload) && $payload['title'] !== null
                ? trim((string) $payload['title'])
                : null,
            subtitle: array_key_exists('subtitle', $payload) && $payload['subtitle'] !== null
                ? trim((string) $payload['subtitle'])
                : null,
            position: (int) ($payload['position'] ?? 0),
            isVisible: (bool) ($payload['is_visible'] ?? true),
            configuration: $config,
        );
    }
}
