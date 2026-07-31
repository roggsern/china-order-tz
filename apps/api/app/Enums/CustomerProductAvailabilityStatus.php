<?php

namespace App\Enums;

enum CustomerProductAvailabilityStatus: string
{
    case Available = 'available';
    case OutOfStock = 'out_of_stock';
    case Unavailable = 'unavailable';
}
