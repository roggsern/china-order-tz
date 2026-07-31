<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductMediaType;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class AdminProductVariantMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_product_level_media_list_excludes_variant_bound_rows(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Blue',
            'is_active' => true,
        ]);

        $productMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/product.jpg',
            'is_active' => true,
        ]);
        ProductMedia::factory()->forVariant($variant)->create([
            'product_id' => $product->id,
            'url' => '/storage/variant.jpg',
            'is_active' => true,
        ]);

        $ids = collect(
            $this->getJson('/api/v1/admin/products/'.$product->id.'/media')
                ->assertOk()
                ->json('data'),
        )->pluck('id');

        $this->assertTrue($ids->contains($productMedia->id));
        $this->assertCount(1, $ids);

        $row = collect(
            $this->getJson('/api/v1/admin/products/'.$product->id.'/media')->json('data'),
        )->first();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('product_variant_id', $row);
        $this->assertNull($row['product_variant_id']);
    }

    public function test_can_create_and_list_variant_media(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black / Medium',
            'is_active' => true,
        ]);

        $create = $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'url' => '/storage/black-medium.jpg',
            'product_variant_id' => $variant->id,
            'alt_text' => 'Black medium',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.product_variant_id', $variant->id)
            ->assertJsonPath('data.variant_name', 'Black / Medium')
            ->assertJsonPath('data.alt_text', 'Black medium');

        $mediaId = $create->json('data.id');

        $this->assertDatabaseHas('product_media', [
            'id' => $mediaId,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id.'/media?product_variant_id='.$variant->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mediaId)
            ->assertJsonPath('data.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.0.variant_name', 'Black / Medium');

        // Product-level list remains empty of this row.
        $this->getJson('/api/v1/admin/products/'.$product->id.'/media')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_variant_file_upload_does_not_create_legacy_product_image(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('variant.jpg'),
            'product_variant_id' => $variant->id,
        ])->assertCreated()
            ->assertJsonPath('data.product_variant_id', $variant->id);

        $this->assertSame(1, ProductMedia::query()->count());
        $this->assertSame(0, \App\Models\ProductImage::query()->count());
        $this->assertNotNull(ProductMedia::query()->value('product_variant_id'));
    }

    public function test_rejects_variant_from_another_product(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $foreignVariant = ProductVariant::factory()->create([
            'product_id' => $other->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'url' => '/storage/foreign.jpg',
            'product_variant_id' => $foreignVariant->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_rejects_inactive_variant(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'url' => '/storage/inactive.jpg',
            'product_variant_id' => $variant->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_media_write_requires_catalog_update_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::CATALOG_VIEW])->create(),
        );

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'url' => '/storage/denied.jpg',
            'product_variant_id' => $variant->id,
        ])->assertForbidden();
    }

    public function test_media_list_requires_catalog_view_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::ORDERS_VIEW])->create(),
        );

        $product = Product::factory()->create();

        $this->getJson('/api/v1/admin/products/'.$product->id.'/media')->assertForbidden();
    }

    public function test_product_media_relation_ignores_variant_rows_for_regression(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'type' => ProductMediaType::Image,
            'url' => '/storage/product.jpg',
        ]);
        ProductMedia::factory()->forVariant($variant)->create([
            'product_id' => $product->id,
            'url' => '/storage/variant.jpg',
        ]);

        $this->assertSame(1, $product->media()->count());
        $this->assertSame(1, $variant->media()->count());
        $this->assertNull($product->media()->first()->product_variant_id);
    }
}
