<?php

namespace Tests\Unit\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ShippingMethod;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\Store;
use App\Services\Catalog\ShippingConsistencyAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingConsistencyAuditorTest extends TestCase
{
    use RefreshDatabase;

    private ShippingConsistencyAuditor $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditor = app(ShippingConsistencyAuditor::class);
    }

    public function test_detects_china_products_without_shipping_options(): void
    {
        Product::factory()->chinaImport()->create([
            'name' => 'China Missing Shipping',
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ]);

        $report = $this->auditor->audit();

        $this->assertSame(1, $report['china_import_missing_shipping']['total']);
        $this->assertSame('China Missing Shipping', $report['china_import_missing_shipping']['products'][0]['name']);
        $this->assertSame(0, $report['china_import_missing_shipping']['products'][0]['shipping_options_count']);
    }

    public function test_detects_tz_products_with_freight_data(): void
    {
        $store = Store::query()->create([
            'code' => 'TZAUD',
            'name' => 'TZ Audit Store',
            'slug' => 'tz-audit-store',
            'is_active' => true,
        ]);

        Product::factory()->tzLocal()->create([
            'name' => 'TZ With Freight',
            'store_id' => $store->id,
            'air_shipping_price' => 8000,
            'sea_shipping_price' => null,
        ]);

        ProductShippingOption::query()->create([
            'product_id' => Product::factory()->tzLocal()->create([
                'name' => 'TZ With Options',
                'store_id' => $store->id,
            ])->id,
            'transport_mode' => ShippingMethod::Air,
            'price' => 5000,
            'currency' => 'TZS',
            'is_available' => true,
            'sort_order' => 0,
        ]);

        $report = $this->auditor->audit();

        $this->assertSame(2, $report['tz_local_invalid_freight']['total']);
        $this->assertTrue(
            collect($report['tz_local_invalid_freight']['products'])
                ->pluck('name')
                ->contains('TZ With Freight'),
        );
        $this->assertTrue(
            collect($report['tz_local_invalid_freight']['products'])
                ->pluck('name')
                ->contains('TZ With Options'),
        );
    }

    public function test_ignores_valid_china_products(): void
    {
        $product = Product::factory()->chinaImport()->create([
            'name' => 'Valid China Product',
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ]);

        ProductShippingOption::query()->create([
            'product_id' => $product->id,
            'transport_mode' => ShippingMethod::Air,
            'price' => 12000,
            'currency' => 'TZS',
            'is_available' => true,
            'sort_order' => 0,
        ]);

        $report = $this->auditor->audit();

        $this->assertSame(0, $report['china_import_missing_shipping']['total']);
    }

    public function test_ignores_valid_tz_products(): void
    {
        $store = Store::query()->create([
            'code' => 'TZOK',
            'name' => 'TZ OK Store',
            'slug' => 'tz-ok-store',
            'is_active' => true,
        ]);

        Product::factory()->tzLocal()->create([
            'name' => 'Valid TZ Product',
            'store_id' => $store->id,
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ]);

        $report = $this->auditor->audit();

        $this->assertSame(0, $report['tz_local_invalid_freight']['total']);
    }

    public function test_detects_legacy_china_rows_missing_commerce_channel(): void
    {
        $product = Product::factory()->create([
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'name' => 'Legacy China Row',
        ]);
        $product->forceFill(['commerce_channel_id' => null])->save();

        $report = $this->auditor->audit();

        $this->assertSame(1, $report['legacy_missing_commerce_channel']['total']);
        $this->assertSame('Legacy China Row', $report['legacy_missing_commerce_channel']['products'][0]['name']);
    }
}
