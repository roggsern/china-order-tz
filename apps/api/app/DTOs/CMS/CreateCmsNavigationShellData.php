<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;

final class CreateCmsNavigationShellData
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly CmsCommerceContext $commerceContext,
        public readonly CmsNavigationType $navigationType,
        public readonly CmsStatus $status,
        public readonly bool $isDefault,
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
            navigationType: CmsNavigationType::from((string) $payload['navigation_type']),
            status: isset($payload['status'])
                ? CmsStatus::from((string) $payload['status'])
                : CmsStatus::Draft,
            isDefault: (bool) ($payload['is_default'] ?? false),
        );
    }
}
