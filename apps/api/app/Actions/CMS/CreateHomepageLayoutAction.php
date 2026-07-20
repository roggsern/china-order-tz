<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\CreateHomepageLayoutData;
use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Services\CMS\CmsHomepageService;

class CreateHomepageLayoutAction
{
    public function __construct(private readonly CmsHomepageService $cms) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?Admin $admin = null): CmsHomepageLayout
    {
        return $this->cms->createLayout(CreateHomepageLayoutData::fromArray($payload), $admin);
    }
}
