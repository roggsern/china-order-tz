<?php

namespace App\Enums;

enum GrowthJourneyTrigger: string
{
    case Registration = 'registration';
    case InactiveDays = 'inactive_days';
    case VipThreshold = 'vip_threshold';
    case Birthday = 'birthday';
    case Manual = 'manual';
}
