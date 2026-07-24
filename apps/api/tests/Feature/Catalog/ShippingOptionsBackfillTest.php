<?php

namespace Tests\Feature\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ShippingMethod;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\ProductShippingBackfillLog;
use App\Models\ProductShippingOption;
use App\Models\Store;
use App\Services\Catalog\ShippingOptionsBackfillAuditor;
use App\Services\Catalog\ShippingOptionsBackfillService;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShippingOptionsBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function chinaProduct(array $overrides = []): Product
    {
        $china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();

        return Product::factory()->chinaImport()->create(array_merge([
            'commerce_channel_id' => $china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ], $overrides));
    }

    public function test_auditor_detects_china_products_missing_shipping_options(): void
    {
        $this->chinaProduct([
            'name' => 'Needs Air Backfill',
            'air_shipping_price' => 12000,
        ]);

        $report = app(ShippingOptionsBackfillAuditor::class)->audit();

        $this->assertSame(1, $report['total_eligible']);
        $this->assertSame(['air'], $report['products'][0]['planned_modes']);
    }

    public function test_backfill_creates_air_option(): void
    {
        $product = $this->chinaProduct([
            'air_shipping_price' => 12000,
            'sea_shipping_price' => null,
        ]);

        $result = app(ShippingOptionsBackfillService::class)->backfill(['dry_run' => false]);

        $this->assertSame(1, $result['backfilled']);
        $this->assertTrue(
            app(ProductShippingOptionEngine::class)->hasPublishableShippingOption($product->fresh() ?? $product),
        );
        $this->assertDatabaseHas('product_shipping_options', [
            'product_id' => $product->id,
            'transport_mode' => ShippingMethod::Air->value,
            'price' => 12000,
            'is_available' => true,
        ]);
    }

    public function test_backfill_creates_sea_option(): void
    {
        $product = $this->chinaProduct([
            'air_shipping_price' => null,
            'sea_shipping_price' => 4500,
        ]);

        app(ShippingOptionsBackfillService::class)->backfill(['dry_run' => false]);

        $this->assertDatabaseHas('product_shipping_options', [
            'product_id' => $product->id,
            'transport_mode' => ShippingMethod::Sea->value,
            'price' => 4500,
            'is_available' => true,
        ]);
    }

    public function test_backfill_creates_both_options(): void
    {
        $product = $this->chinaProduct([
            'air_shipping_price' => 9000,
            'sea_shipping_price' => 3500,
        ]);

        app(ShippingOptionsBackfillService::class)->backfill(['dry_run' => false]);

        $this->assertSame(2, $product->fresh()?->shippingOptions()->count());
    }

    public function test_backfill_skips_products_already_having_options(): void
    {
        $product = $this->chinaProduct([
            'air_shipping_price' => 9000,
            'sea_shipping_price' => null,
        ]);

        ProductShippingOption::query()->create([
            'product_id' => $product->id,
            'transport_mode' => ShippingMethod::Air,
            'price' => 11000,
            'currency' => 'TZS',
            'is_available' => true,
            'sort_order' => 0,
        ]);

        $fresh = $product->fresh(['shippingOptions']) ?? $product;
        $this->assertFalse(app(ShippingOptionsBackfillAuditor::class)->isEligible($fresh));
        $this->assertSame(0, app(ShippingOptionsBackfillAuditor::class)->audit()['total_eligible']);

        $result = app(ShippingOptionsBackfillService::class)->backfill(['dry_run' => false]);

        $this->assertSame(0, $result['backfilled']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $product->fresh()?->shippingOptions()->count());
    }

    public function test_backfill_skips_tz_local(): void
    {
        $store = Store::query()->create([
            'code' => 'TZSHP',
            'name' => 'TZ Shipping Store',
            'slug' => 'tz-shipping-store',
            'is_active' => true,
        ]);

        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        Product::factory()->tzLocal()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $tz->id,
            'air_shipping_price' => 8000,
        ]);

        $this->chinaProduct([
            'air_shipping_price' => 12000,
        ]);

        $result = app(ShippingOptionsBackfillService::class)->backfill(['dry_run' => false]);

        $this->assertSame(1, $result['backfilled']);
        $this->assertDatabaseCount('product_shipping_options', 1);
    }

    public function test_rollback_removes_only_created_options(): void
    {
        $product = $this->chinaProduct([
            'air_shipping_price' => 12000,
            'sea_shipping_price' => 4500,
        ]);

        $existing = ProductShippingOption::query()->create([
            'product_id' => $product->id,
            'transport_mode' => ShippingMethod::Sea,
            'price' => 1,
            'currency' => 'TZS',
            'is_available' => false,
            'sort_order' => 1,
        ]);

        $service = app(ShippingOptionsBackfillService::class);
        $result = $service->backfill(['dry_run' => false]);
        $this->assertSame(1, $result['backfilled']);

        $createdAir = ProductShippingOption::query()
            ->where('product_id', $product->id)
            ->where('transport_mode', ShippingMethod::Air->value)
            ->firstOrFail();

        $service->rollback($result['batch_id'], dryRun: false);

        $this->assertSoftDeleted('product_shipping_options', ['id' => $createdAir->id]);
        $this->assertDatabaseHas('product_shipping_options', [
            'id' => $existing->id,
            'price' => 1,
            'is_available' => false,
        ]);
        $this->assertDatabaseHas('product_shipping_backfill_logs', [
            'batch_id' => $result['batch_id'],
            'product_id' => $product->id,
            'action' => ProductShippingBackfillLog::ACTION_ROLLED_BACK,
        ]);
    }

    public function test_rollback_fails_for_unknown_batch(): void
    {
        $this->expectException(ValidationException::class);

        app(ShippingOptionsBackfillService::class)->rollback(
            '00000000-0000-4000-8000-000000000001',
            dryRun: false,
        );
    }
}
