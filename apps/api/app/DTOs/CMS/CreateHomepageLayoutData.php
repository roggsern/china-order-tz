<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;

final class CreateHomepageLayoutData
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly CmsCommerceContext $commerceContext,
        public readonly CmsStatus $status = CmsStatus::Draft,
        public readonly bool $isDefault = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: trim((string) $payload['name']),
            slug: trim((string) $payload['slug']),
            commerceContext: CmsCommerceContext::from((string) $payload['commerce_context']),
            status: isset($payload['status'])
                ? CmsStatus::from((string) $payload['status'])
                : CmsStatus::Draft,
            isDefault: (bool) ($payload['is_default'] ?? false),
        );
    }
}
