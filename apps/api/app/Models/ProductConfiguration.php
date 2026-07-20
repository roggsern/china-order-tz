<?php

namespace App\Models;

/**
 * Domain name for a sellable Product Configuration.
 *
 * Phase A: physically stored in `product_variants` for backward compatibility
 * with cart/orders/inventory FKs. Admin/storefront/POS should prefer this model
 * name going forward; ProductVariant remains as a legacy alias.
 */
class ProductConfiguration extends ProductVariant
{
    //
}
