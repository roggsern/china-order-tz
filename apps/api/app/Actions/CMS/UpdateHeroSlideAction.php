<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\UpdateHeroSlideData;
use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Services\CMS\CmsHeroSlideService;

class UpdateHeroSlideAction
{
    public function __construct(private readonly CmsHeroSlideService $heroes) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsHeroSlide $slide, array $payload, ?Admin $admin = null): CmsHeroSlide
    {
        return $this->heroes->update($slide, UpdateHeroSlideData::fromArray($payload), $admin);
    }
}
