<?php

namespace Tests\Unit\Models;

use App\Enums\ShippingMethod;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\Supplier;
use Tests\TestCase;

class ProductRequiresChinaShippingTest extends TestCase
{
    public function test_china_import_product_requires_china_shipping(): void
    {
        $product = Product::factory()->chinaImport()->create([
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ]);

        $this->assertTrue($product->requiresChinaShipping());
    }

    public function test_tz_local_product_does_not_require_china_shipping(): void
    {
        $product = Product::factory()->tzLocal()->create();

        $this->assertFalse($product->requiresChinaShipping());
    }

    public function test_tz_local_with_china_supplier_shipping_options_and_legacy_columns_stays_false(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'supplier_id' => Supplier::factory()->china(),
            'air_shipping_price' => 18000,
            'sea_shipping_price' => 9500,
        ]);

        ProductShippingOption::query()->create([
            'product_id' => $product->id,
            'transport_mode' => ShippingMethod::Air,
            'price' => 18000,
            'currency' => 'TZS',
            'is_available' => true,
            'sort_order' => 0,
        ]);

        ProductShippingOption::query()->create([
            'product_id' => $product->id,
            'transport_mode' => ShippingMethod::Sea,
            'price' => 9500,
            'currency' => 'TZS',
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $product->load('supplier', 'shippingOptions', 'commerceChannel');

        $this->assertTrue($product->isFromChina());
        $this->assertFalse($product->requiresChinaShipping());
    }

    public function test_legacy_fulfillment_source_fallback_resolves_china_import(): void
    {
        $product = Product::factory()->chinaImport()->create();
        $product->forceFill(['commerce_channel_id' => null])->save();

        $this->assertTrue($product->fresh()->requiresChinaShipping());
    }

    public function test_legacy_fulfillment_source_fallback_resolves_tz_local(): void
    {
        $product = Product::factory()->tzLocal()->create();
        $product->forceFill(['commerce_channel_id' => null])->save();

        $this->assertFalse($product->fresh()->requiresChinaShipping());
    }
}
