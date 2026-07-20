<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\ReorderHeroSlidesData;
use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHeroSlideService;
use Illuminate\Database\Eloquent\Collection;

class ReorderHeroSlidesAction
{
    public function __construct(private readonly CmsHeroSlideService $heroes) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, CmsHeroSlide>
     */
    public function handle(CmsHomepageSection $section, array $payload, ?Admin $admin = null): Collection
    {
        return $this->heroes->reorder($section, ReorderHeroSlidesData::fromArray($payload), $admin);
    }
}
