<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsHomepageSectionType;

final class UpdateHomepageSectionData
{
    /**
     * @param  array<string, mixed>|null  $configuration
     * @param  array<string, true>  $present
     */
    public function __construct(
        public readonly ?CmsHomepageSectionType $sectionType = null,
        public readonly ?string $title = null,
        public readonly ?string $subtitle = null,
        public readonly ?int $position = null,
        public readonly ?bool $isVisible = null,
        public readonly ?array $configuration = null,
        public readonly array $present = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $present = [];
        foreach (['section_type', 'title', 'subtitle', 'position', 'is_visible', 'configuration'] as $key) {
            if (array_key_exists($key, $payload)) {
                $present[$key] = true;
            }
        }

        $configuration = null;
        if (array_key_exists('configuration', $payload)) {
            $configuration = is_array($payload['configuration']) ? $payload['configuration'] : [];
        }

        return new self(
            sectionType: array_key_exists('section_type', $payload)
                ? CmsHomepageSectionType::from((string) $payload['section_type'])
                : null,
            title: array_key_exists('title', $payload)
                ? ($payload['title'] !== null ? trim((string) $payload['title']) : null)
                : null,
            subtitle: array_key_exists('subtitle', $payload)
                ? ($payload['subtitle'] !== null ? trim((string) $payload['subtitle']) : null)
                : null,
            position: array_key_exists('position', $payload) ? (int) $payload['position'] : null,
            isVisible: array_key_exists('is_visible', $payload) ? (bool) $payload['is_visible'] : null,
            configuration: $configuration,
            present: $present,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->present[$key]);
    }
}
