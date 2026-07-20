<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Services\CMS\CmsFeaturedContentService;

class DeleteFeaturedContentAction
{
    public function __construct(private readonly CmsFeaturedContentService $featured) {}

    public function handle(CmsFeaturedContent $featured, ?Admin $admin = null): void
    {
        $this->featured->delete($featured, $admin);
    }
}
