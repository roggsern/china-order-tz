<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Services\CMS\CmsHomepageService;

class ArchiveHomepageLayoutAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    public function handle(CmsHomepageLayout $layout, ?Admin $admin = null): CmsHomepageLayout
    {
        return $this->cms->archiveLayout($layout, $admin);
    }
}
