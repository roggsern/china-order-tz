<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHomepageService;

class DeleteHomepageSectionAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    public function handle(CmsHomepageSection $section, ?Admin $admin = null): void
    {
        $this->cms->deleteSection($section, $admin);
    }
}
