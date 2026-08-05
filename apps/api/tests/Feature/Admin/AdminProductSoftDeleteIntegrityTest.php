<?php

namespace Tests\Feature\Admin;

use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantPrice;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductSoftDeleteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_active_product_with_priced_variant_lists_successfully(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create([
            'name' => 'Active SoftDelete List',
            'price' => 10000,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 12000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $row = collect(
            $this->getJson('/api/v1/admin/products?search=Active+SoftDelete+List')
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $product->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['variants_count']);
        $this->assertSame('12000.00', $row['price_range']['min'] ?? null);
        $variantRow = collect($row['variants'] ?? [])->firstWhere('id', $variant->id);
        $this->assertSame('12000.00', $variantRow['effective_price'] ?? null);
    }

    public function test_soft_deleted_parent_with_orphan_variants_does_not_crash_active_or_trash_listing(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $alive = Product::factory()->tzLocal()->create([
            'name' => 'Alive Sibling',
            'price' => 5000,
        ]);

        $trashed = Product::factory()->tzLocal()->create([
            'name' => 'BLAZER SoftDelete Crash',
            'price' => 45000,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $trashed->id,
            'price' => null,
            'is_active' => true,
        ]);

        // Legacy orphan path: soft-delete product without cascading variants.
        $trashed->delete();
        $this->assertNull($variant->fresh()->deleted_at);
        $this->assertNull($variant->fresh()->product);

        $this->getJson('/api/v1/admin/products?search=Alive+Sibling')
            ->assertOk()
            ->assertJsonFragment(['id' => $alive->id]);

        $activeIds = collect($this->getJson('/api/v1/admin/products')->assertOk()->json('data'))
            ->pluck('id');
        $this->assertFalse($activeIds->contains($trashed->id));

        $trashResponse = $this->getJson('/api/v1/admin/products?trashed=1&per_page=100')
            ->assertOk();

        $row = collect($trashResponse->json('data'))->firstWhere('id', $trashed->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['catalog_integrity']['has_orphaned_active_variants'] ?? false);
        $this->assertSame(1, $row['catalog_integrity']['orphaned_active_variants_count'] ?? null);

        $variantRow = collect($row['variants'] ?? [])->firstWhere('id', $variant->id);
        $this->assertNotNull($variantRow);
        $this->assertSame('45000.00', $variantRow['effective_price'] ?? null);
    }

    public function test_trash_endpoint_lists_soft_deleted_product(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create(['name' => 'Trash Endpoint Product']);
        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->getJson('/api/v1/admin/products/trash')
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id]);
    }

    public function test_effective_price_handles_missing_parent_safely(): void
    {
        $product = Product::factory()->tzLocal()->create(['price' => 33000]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);
        $product->delete();

        $variant->refresh();
        $variant->load('product');
        $this->assertNull($variant->product);
        $this->assertSame('33000.00', $variant->effectivePrice());

        $orphan = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);
        // Simulate fully missing parent price after force path edge cases: null-safe.
        $orphan->setRelation('product', null);
        $orphan->price = null;
        // Parent still resolvable via withTrashed.
        $this->assertSame('33000.00', $orphan->effectivePrice());
    }

    public function test_missing_price_still_fails_publish_validation(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable();

        VariantPrice::query()->where('product_variant_id', $variant->id)->delete();
        $variant->forceFill(['price' => null])->save();

        $fresh = $product->fresh([
            'commerceChannel',
            'catalogProductType',
            'category',
            'inventory',
            'shippingOptions',
            'variants.prices',
            'variants.inventories',
            'store',
        ]);

        try {
            app(ProductPurchasabilityPolicy::class)->assertPublishable($fresh ?? $product);
            $this->fail('Expected ValidationException when variant retail price is missing.');
        } catch (ValidationException $exception) {
            $this->assertTrue(
                array_key_exists('variants', $exception->errors())
                || array_key_exists('price', $exception->errors())
                || array_key_exists('purchasability', $exception->errors()),
            );
        }
    }

    public function test_delete_cascades_variants_and_restore_brings_them_back(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create(['name' => 'Cascade Delete Product']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->assertNotNull($product->fresh());
        $this->assertTrue($product->fresh()->trashed());
        $this->assertNotNull(ProductVariant::withTrashed()->find($variant->id)?->deleted_at);
        $this->assertNull(ProductVariant::query()->find($variant->id));

        $this->postJson("/api/v1/admin/products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $this->assertFalse($product->fresh()->trashed());
        $this->assertNull($variant->fresh()->deleted_at);

        $activeIds = collect($this->getJson('/api/v1/admin/products?search=Cascade+Delete+Product')
            ->assertOk()
            ->json('data'))->pluck('id');
        $this->assertTrue($activeIds->contains($product->id));
    }

    public function test_force_delete_removes_product_and_variants(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();
        $this->deleteJson("/api/v1/admin/products/{$product->id}/force")->assertOk();

        $this->assertNull(Product::withTrashed()->find($product->id));
        $this->assertNull(ProductVariant::withTrashed()->find($variant->id));
    }

    public function test_order_item_snapshot_readable_after_product_soft_delete(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $user = User::factory()->create();
        $product = Product::factory()->tzLocal()->create(['name' => 'Snapshot BLAZER']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'Snapshot BLAZER',
            'quantity' => 1,
        ]);
        $snapshotName = $item->product_name_snapshot;
        $snapshotPrice = (string) $item->unit_price_snapshot;

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $item->refresh();
        $this->assertSame('Snapshot BLAZER', $item->product_name_snapshot);
        $this->assertSame($snapshotName, $item->product_name_snapshot);
        $this->assertSame($snapshotPrice, (string) $item->unit_price_snapshot);
        $this->assertTrue($product->fresh()->trashed());
    }
}
