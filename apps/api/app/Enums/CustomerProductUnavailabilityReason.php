<?php

namespace App\Enums;

enum CustomerProductUnavailabilityReason: string
{
    case MissingInventoryPolicy = 'missing_inventory_policy';
    case InvalidPricing = 'invalid_pricing';
    case MissingSellableVariant = 'missing_sellable_variant';
    case LifecycleInactive = 'lifecycle_inactive';
    case MissingShippingOptions = 'missing_shipping_options';
    case NoPurchasablePath = 'no_purchasable_path';
    case Unavailable = 'unavailable';
}
