<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use Carbon\Carbon;

final class UpdateCmsCampaignData
{
    /**
     * @param  array<string, true>  $present
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $description = null,
        public readonly ?CmsCommerceContext $commerceContext = null,
        public readonly ?CmsStatus $status = null,
        public readonly ?Carbon $startsAt = null,
        public readonly ?Carbon $endsAt = null,
        public readonly ?int $priority = null,
        public readonly ?bool $isDefault = null,
        public readonly ?string $homepageLayoutId = null,
        public readonly array $present = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $keys = [
            'name', 'slug', 'description', 'commerce_context', 'status',
            'starts_at', 'ends_at', 'priority', 'is_default', 'cms_homepage_layout_id',
        ];
        $present = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $present[$key] = true;
            }
        }

        return new self(
            name: array_key_exists('name', $payload) ? trim((string) $payload['name']) : null,
            slug: array_key_exists('slug', $payload) ? trim((string) $payload['slug']) : null,
            description: array_key_exists('description', $payload)
                ? ($payload['description'] !== null ? trim((string) $payload['description']) : null)
                : null,
            commerceContext: array_key_exists('commerce_context', $payload)
                ? CmsCommerceContext::from((string) $payload['commerce_context'])
                : null,
            status: array_key_exists('status', $payload)
                ? CmsStatus::from((string) $payload['status'])
                : null,
            startsAt: array_key_exists('starts_at', $payload)
                ? ($payload['starts_at'] !== null ? Carbon::parse($payload['starts_at']) : null)
                : null,
            endsAt: array_key_exists('ends_at', $payload)
                ? ($payload['ends_at'] !== null ? Carbon::parse($payload['ends_at']) : null)
                : null,
            priority: array_key_exists('priority', $payload) ? (int) $payload['priority'] : null,
            isDefault: array_key_exists('is_default', $payload) ? (bool) $payload['is_default'] : null,
            homepageLayoutId: array_key_exists('cms_homepage_layout_id', $payload)
                ? ($payload['cms_homepage_layout_id'] !== null ? (string) $payload['cms_homepage_layout_id'] : null)
                : null,
            present: $present,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->present[$key]);
    }
}
