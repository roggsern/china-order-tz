<?php

namespace Tests\Feature\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductStoreBackfillLog;
use App\Models\Store;
use App\Services\Catalog\TzLocalStoreOwnershipBackfill;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TzLocalStoreOwnershipBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_assigns_store_and_passes_publish_validation(): void
    {
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $store = Store::query()->create([
            'code' => 'ZION',
            'name' => 'Zion Mode',
            'slug' => 'zion-mode',
            'is_active' => true,
        ]);

        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create([
            'parent_id' => null,
            'store_id' => $store->id,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
            'store_id' => $store->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => null,
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'is_demo' => false,
            'visibility' => ProductVisibility::Public,
            'price' => 15000,
        ]);

        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
        ]);

        try {
            app(ProductPurchasabilityPolicy::class)->assertPublishable(
                $product->fresh(['commerceChannel', 'catalogProductType', 'category', 'inventory']),
            );
            $this->fail('Expected ValidationException before store assignment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('store_id', $exception->errors());
        }

        $result = app(TzLocalStoreOwnershipBackfill::class)->backfill([
            'dry_run' => false,
            'include_listed' => true,
        ]);

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'store_id' => $store->id,
        ]);
        $this->assertDatabaseHas('product_store_backfill_logs', [
            'batch_id' => $result['batch_id'],
            'product_id' => $product->id,
            'assigned_store_id' => $store->id,
            'action' => ProductStoreBackfillLog::ACTION_ASSIGNED,
        ]);

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'variants.prices',
            'variants.inventories',
        ]);

        app(ProductPurchasabilityPolicy::class)->assertPublishable($fresh ?? $product);
        $this->assertTrue(true);
    }

    public function test_backfill_skips_active_products_without_include_listed_flag(): void
    {
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $store = Store::query()->create([
            'code' => 'ROVI',
            'name' => 'Rovi Beauty',
            'slug' => 'rovi-beauty',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => null,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);

        $result = app(TzLocalStoreOwnershipBackfill::class)->backfill([
            'dry_run' => false,
            'store_id' => $store->id,
        ]);

        $this->assertSame(0, $result['assigned']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'store_id' => null,
        ]);
    }

    public function test_backfill_batch_is_reversible(): void
    {
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $store = Store::query()->create([
            'code' => 'PEACHY',
            'name' => 'Peachy Lingerie',
            'slug' => 'peachy-lingerie',
            'is_active' => true,
        ]);
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create([
            'store_id' => $store->id,
        ]);

        $product = Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => null,
            'category_id' => $category->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $backfill = app(TzLocalStoreOwnershipBackfill::class);
        $result = $backfill->backfill(['dry_run' => false]);
        $this->assertSame(1, $result['assigned']);

        $rollback = $backfill->rollback($result['batch_id'], dryRun: false);
        $this->assertSame(1, $rollback['restored']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'store_id' => null,
        ]);
    }
}
