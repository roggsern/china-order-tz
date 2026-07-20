<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\ReorderHomepageSectionsData;
use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHomepageService;
use Illuminate\Database\Eloquent\Collection;

class ReorderHomepageSectionsAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, CmsHomepageSection>
     */
    public function handle(CmsHomepageLayout $layout, array $payload, ?Admin $admin = null): Collection
    {
        return $this->cms->reorderSections($layout, ReorderHomepageSectionsData::fromArray($payload), $admin);
    }
}
