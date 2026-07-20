<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationVisibility;

final class CreateCmsNavigationItemData
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $icon,
        public readonly int $position,
        public readonly CmsNavigationVisibility $visibility,
        public readonly CmsNavigationItemType $itemType,
        public readonly ?CmsCtaTargetType $targetType,
        public readonly ?string $targetValue,
        public readonly bool $isEnabled,
        public readonly ?string $parentId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $targetType = null;
        if (array_key_exists('target_type', $payload) && $payload['target_type'] !== null && $payload['target_type'] !== '') {
            $targetType = CmsCtaTargetType::from((string) $payload['target_type']);
        }

        return new self(
            title: trim((string) $payload['title']),
            icon: isset($payload['icon']) && $payload['icon'] !== null && $payload['icon'] !== ''
                ? trim((string) $payload['icon'])
                : null,
            position: (int) ($payload['position'] ?? 0),
            visibility: isset($payload['visibility'])
                ? CmsNavigationVisibility::from((string) $payload['visibility'])
                : CmsNavigationVisibility::Public,
            itemType: CmsNavigationItemType::from((string) $payload['item_type']),
            targetType: $targetType,
            targetValue: isset($payload['target_value']) && $payload['target_value'] !== null && $payload['target_value'] !== ''
                ? trim((string) $payload['target_value'])
                : null,
            isEnabled: array_key_exists('is_enabled', $payload) ? (bool) $payload['is_enabled'] : true,
            parentId: isset($payload['parent_id']) && $payload['parent_id'] !== null && $payload['parent_id'] !== ''
                ? (string) $payload['parent_id']
                : null,
        );
    }
}
