<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;

final class UpdateCmsNavigationShellData
{
    /**
     * @param  list<string>  $present
     */
    public function __construct(
        public readonly array $present,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?CmsCommerceContext $commerceContext = null,
        public readonly ?CmsNavigationType $navigationType = null,
        public readonly ?CmsStatus $status = null,
        public readonly ?bool $isDefault = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $present = array_keys($payload);

        return new self(
            present: $present,
            name: array_key_exists('name', $payload) ? trim((string) $payload['name']) : null,
            slug: array_key_exists('slug', $payload) ? trim((string) $payload['slug']) : null,
            commerceContext: array_key_exists('commerce_context', $payload) && $payload['commerce_context'] !== null
                ? CmsCommerceContext::from((string) $payload['commerce_context'])
                : null,
            navigationType: array_key_exists('navigation_type', $payload) && $payload['navigation_type'] !== null
                ? CmsNavigationType::from((string) $payload['navigation_type'])
                : null,
            status: array_key_exists('status', $payload) && $payload['status'] !== null
                ? CmsStatus::from((string) $payload['status'])
                : null,
            isDefault: array_key_exists('is_default', $payload) ? (bool) $payload['is_default'] : null,
        );
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->present, true);
    }
}
