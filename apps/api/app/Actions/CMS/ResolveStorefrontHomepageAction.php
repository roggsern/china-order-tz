<?php

namespace App\Actions\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Models\CmsCampaign;
use App\Models\CmsHomepageLayout;
use App\Services\CMS\CmsHomepageService;

class ResolveStorefrontHomepageAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    /**
     * @return array{layout: ?CmsHomepageLayout, campaign: ?CmsCampaign}
     */
    public function handle(
        CmsCommerceContext $context,
        bool $allowGlobalFallback = true,
    ): array {
        return $this->cms->resolveStorefrontExperience($context, $allowGlobalFallback);
    }
}
