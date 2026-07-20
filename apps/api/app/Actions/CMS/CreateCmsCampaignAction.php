<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\CreateCmsCampaignData;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Services\CMS\CmsCampaignService;

class CreateCmsCampaignAction
{
    public function __construct(private readonly CmsCampaignService $campaigns) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?Admin $admin = null): CmsCampaign
    {
        return $this->campaigns->create(CreateCmsCampaignData::fromArray($payload), $admin);
    }
}
