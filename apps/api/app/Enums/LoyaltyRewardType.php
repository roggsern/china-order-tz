<?php

namespace App\Enums;

enum LoyaltyRewardType: string
{
    case DiscountVoucher = 'discount_voucher';
    case FreeProduct = 'free_product';
    case SpecialOffer = 'special_offer';
    case VipBenefit = 'vip_benefit';
}
