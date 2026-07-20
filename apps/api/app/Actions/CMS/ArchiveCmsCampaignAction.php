<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Services\CMS\CmsCampaignService;

class ArchiveCmsCampaignAction
{
    public function __construct(private readonly CmsCampaignService $campaigns) {}

    public function handle(CmsCampaign $campaign, ?Admin $admin = null): CmsCampaign
    {
        return $this->campaigns->archive($campaign, $admin);
    }
}
