<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\UpdateHomepageLayoutData;
use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Services\CMS\CmsHomepageService;

class UpdateHomepageLayoutAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsHomepageLayout $layout, array $payload, ?Admin $admin = null): CmsHomepageLayout
    {
        return $this->cms->updateLayout($layout, UpdateHomepageLayoutData::fromArray($payload), $admin);
    }
}
