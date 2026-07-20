<?php

namespace App\Enums;

enum GrowthCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
