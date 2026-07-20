<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationVisibility;

final class UpdateCmsNavigationItemData
{
    /**
     * @param  list<string>  $present
     */
    public function __construct(
        public readonly array $present,
        public readonly ?string $title = null,
        public readonly ?string $icon = null,
        public readonly ?int $position = null,
        public readonly ?CmsNavigationVisibility $visibility = null,
        public readonly ?CmsNavigationItemType $itemType = null,
        public readonly ?CmsCtaTargetType $targetType = null,
        public readonly ?string $targetValue = null,
        public readonly ?bool $isEnabled = null,
        public readonly ?string $parentId = null,
        public readonly bool $clearParent = false,
        public readonly bool $clearIcon = false,
        public readonly bool $clearTargetType = false,
        public readonly bool $clearTargetValue = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $present = array_keys($payload);

        $targetType = null;
        $clearTargetType = false;
        if (array_key_exists('target_type', $payload)) {
            if ($payload['target_type'] === null || $payload['target_type'] === '') {
                $clearTargetType = true;
            } else {
                $targetType = CmsCtaTargetType::from((string) $payload['target_type']);
            }
        }

        $parentId = null;
        $clearParent = false;
        if (array_key_exists('parent_id', $payload)) {
            if ($payload['parent_id'] === null || $payload['parent_id'] === '') {
                $clearParent = true;
            } else {
                $parentId = (string) $payload['parent_id'];
            }
        }

        $icon = null;
        $clearIcon = false;
        if (array_key_exists('icon', $payload)) {
            if ($payload['icon'] === null || $payload['icon'] === '') {
                $clearIcon = true;
            } else {
                $icon = trim((string) $payload['icon']);
            }
        }

        $targetValue = null;
        $clearTargetValue = false;
        if (array_key_exists('target_value', $payload)) {
            if ($payload['target_value'] === null || $payload['target_value'] === '') {
                $clearTargetValue = true;
            } else {
                $targetValue = trim((string) $payload['target_value']);
            }
        }

        return new self(
            present: $present,
            title: array_key_exists('title', $payload) ? trim((string) $payload['title']) : null,
            icon: $icon,
            position: array_key_exists('position', $payload) ? (int) $payload['position'] : null,
            visibility: array_key_exists('visibility', $payload) && $payload['visibility'] !== null
                ? CmsNavigationVisibility::from((string) $payload['visibility'])
                : null,
            itemType: array_key_exists('item_type', $payload) && $payload['item_type'] !== null
                ? CmsNavigationItemType::from((string) $payload['item_type'])
                : null,
            targetType: $targetType,
            targetValue: $targetValue,
            isEnabled: array_key_exists('is_enabled', $payload) ? (bool) $payload['is_enabled'] : null,
            parentId: $parentId,
            clearParent: $clearParent,
            clearIcon: $clearIcon,
            clearTargetType: $clearTargetType,
            clearTargetValue: $clearTargetValue,
        );
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->present, true);
    }
}
