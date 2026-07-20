<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\CreateHomepageSectionData;
use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHomepageService;

class CreateHomepageSectionAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        CmsHomepageLayout $layout,
        array $payload,
        ?Admin $admin = null,
    ): CmsHomepageSection {
        return $this->cms->createSection($layout, CreateHomepageSectionData::fromArray($payload), $admin);
    }
}
