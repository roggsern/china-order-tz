<?php

namespace App\Enums;

enum GrowthCampaignType: string
{
    case Promotion = 'promotion';
    case Announcement = 'announcement';
    case NewProduct = 'new_product';
    case Retention = 'retention';
    case Birthday = 'birthday';
    case Winback = 'winback';
    case Vip = 'vip';
}
