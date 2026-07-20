<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;

final class UpdateHomepageLayoutData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?CmsCommerceContext $commerceContext = null,
        public readonly ?CmsStatus $status = null,
        public readonly ?bool $isDefault = null,
        /** @var array<string, true> */
        public readonly array $present = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $present = [];
        foreach (['name', 'slug', 'commerce_context', 'status', 'is_default'] as $key) {
            if (array_key_exists($key, $payload)) {
                $present[$key] = true;
            }
        }

        return new self(
            name: array_key_exists('name', $payload) ? trim((string) $payload['name']) : null,
            slug: array_key_exists('slug', $payload) ? trim((string) $payload['slug']) : null,
            commerceContext: array_key_exists('commerce_context', $payload)
                ? CmsCommerceContext::from((string) $payload['commerce_context'])
                : null,
            status: array_key_exists('status', $payload)
                ? CmsStatus::from((string) $payload['status'])
                : null,
            isDefault: array_key_exists('is_default', $payload)
                ? (bool) $payload['is_default']
                : null,
            present: $present,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->present[$key]);
    }
}
