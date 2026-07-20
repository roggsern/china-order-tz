<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\UpdateCmsCampaignData;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Services\CMS\CmsCampaignService;

class UpdateCmsCampaignAction
{
    public function __construct(private readonly CmsCampaignService $campaigns) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsCampaign $campaign, array $payload, ?Admin $admin = null): CmsCampaign
    {
        return $this->campaigns->update($campaign, UpdateCmsCampaignData::fromArray($payload), $admin);
    }
}
