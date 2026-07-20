<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\UpdateHomepageSectionData;
use App\Models\Admin;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHomepageService;

class UpdateHomepageSectionAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        CmsHomepageSection $section,
        array $payload,
        ?Admin $admin = null,
    ): CmsHomepageSection {
        return $this->cms->updateSection($section, UpdateHomepageSectionData::fromArray($payload), $admin);
    }
}
