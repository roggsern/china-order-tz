<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use Carbon\Carbon;

final class CreateCmsCampaignData
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly CmsCommerceContext $commerceContext,
        public readonly CmsStatus $status,
        public readonly ?Carbon $startsAt,
        public readonly ?Carbon $endsAt,
        public readonly int $priority,
        public readonly bool $isDefault,
        public readonly ?string $homepageLayoutId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: trim((string) $payload['name']),
            slug: trim((string) $payload['slug']),
            description: isset($payload['description']) && $payload['description'] !== null
                ? trim((string) $payload['description'])
                : null,
            commerceContext: CmsCommerceContext::from((string) $payload['commerce_context']),
            status: isset($payload['status'])
                ? CmsStatus::from((string) $payload['status'])
                : CmsStatus::Draft,
            startsAt: isset($payload['starts_at']) && $payload['starts_at'] !== null
                ? Carbon::parse($payload['starts_at'])
                : null,
            endsAt: isset($payload['ends_at']) && $payload['ends_at'] !== null
                ? Carbon::parse($payload['ends_at'])
                : null,
            priority: (int) ($payload['priority'] ?? 0),
            isDefault: (bool) ($payload['is_default'] ?? false),
            homepageLayoutId: isset($payload['cms_homepage_layout_id'])
                ? (string) $payload['cms_homepage_layout_id']
                : null,
        );
    }
}
