<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Services\CMS\CmsHomepageService;

class SetHomepageLayoutDefaultAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    public function handle(CmsHomepageLayout $layout, ?Admin $admin = null): CmsHomepageLayout
    {
        return $this->cms->setDefault($layout, $admin);
    }
}
