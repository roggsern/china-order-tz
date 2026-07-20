<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Services\CMS\CmsHeroSlideService;

class DeleteHeroSlideAction
{
    public function __construct(private readonly CmsHeroSlideService $heroes) {}

    public function handle(CmsHeroSlide $slide, ?Admin $admin = null): void
    {
        $this->heroes->delete($slide, $admin);
    }
}
