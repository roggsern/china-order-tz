<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Services\CMS\CmsCampaignService;

class ActivateCmsCampaignAction
{
    public function __construct(private readonly CmsCampaignService $campaigns) {}

    public function handle(CmsCampaign $campaign, ?Admin $admin = null): CmsCampaign
    {
        return $this->campaigns->activate($campaign, $admin);
    }
}
