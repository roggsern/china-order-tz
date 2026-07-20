<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\CreateHeroSlideData;
use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHeroSlideService;

class CreateHeroSlideAction
{
    public function __construct(private readonly CmsHeroSlideService $heroes) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsHomepageSection $section, array $payload, ?Admin $admin = null): CmsHeroSlide
    {
        return $this->heroes->create($section, CreateHeroSlideData::fromArray($payload), $admin);
    }
}
