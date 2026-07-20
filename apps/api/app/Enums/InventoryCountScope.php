<?php

namespace App\Enums;

enum InventoryCountScope: string
{
    case Full = 'full';
    case Category = 'category';
    case Selected = 'selected';
}
