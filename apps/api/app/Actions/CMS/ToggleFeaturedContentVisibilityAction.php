<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Services\CMS\CmsFeaturedContentService;

class ToggleFeaturedContentVisibilityAction
{
    public function __construct(private readonly CmsFeaturedContentService $featured) {}

    public function handle(CmsFeaturedContent $featured, ?Admin $admin = null): CmsFeaturedContent
    {
        return $this->featured->toggleVisibility($featured, $admin);
    }
}
