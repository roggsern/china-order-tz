<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\CreateFeaturedContentData;
use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsFeaturedContentService;

class CreateFeaturedContentAction
{
    public function __construct(private readonly CmsFeaturedContentService $featured) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsHomepageSection $section, array $payload, ?Admin $admin = null): CmsFeaturedContent
    {
        return $this->featured->create($section, CreateFeaturedContentData::fromArray($payload), $admin);
    }
}
