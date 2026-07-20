<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Services\CMS\CmsHeroSlideService;

class ToggleHeroSlideVisibilityAction
{
    public function __construct(private readonly CmsHeroSlideService $heroes) {}

    public function handle(CmsHeroSlide $slide, ?Admin $admin = null): CmsHeroSlide
    {
        return $this->heroes->toggleVisibility($slide, $admin);
    }
}
