<?php

namespace App\Enums;

enum LoyaltyEarnRuleType: string
{
    case Spend = 'spend';
    case Product = 'product';
    case Category = 'category';
    case PromotionBonus = 'promotion_bonus';
}
