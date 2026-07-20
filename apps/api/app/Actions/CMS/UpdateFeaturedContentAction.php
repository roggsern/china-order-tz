<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\UpdateFeaturedContentData;
use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Services\CMS\CmsFeaturedContentService;

class UpdateFeaturedContentAction
{
    public function __construct(private readonly CmsFeaturedContentService $featured) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsFeaturedContent $featured, array $payload, ?Admin $admin = null): CmsFeaturedContent
    {
        return $this->featured->update($featured, UpdateFeaturedContentData::fromArray($payload), $admin);
    }
}
