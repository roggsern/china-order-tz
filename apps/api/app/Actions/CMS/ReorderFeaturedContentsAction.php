<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\ReorderFeaturedContentsData;
use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsFeaturedContentService;
use Illuminate\Database\Eloquent\Collection;

class ReorderFeaturedContentsAction
{
    public function __construct(private readonly CmsFeaturedContentService $featured) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, CmsFeaturedContent>
     */
    public function handle(CmsHomepageSection $section, array $payload, ?Admin $admin = null): Collection
    {
        return $this->featured->reorder($section, ReorderFeaturedContentsData::fromArray($payload), $admin);
    }
}
