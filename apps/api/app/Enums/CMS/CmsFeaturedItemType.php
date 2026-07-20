<?php

namespace App\Enums\CMS;

enum CmsFeaturedItemType: string
{
    case Product = 'PRODUCT';
    case Store = 'STORE';
    case Brand = 'BRAND';
    case Category = 'CATEGORY';
}
