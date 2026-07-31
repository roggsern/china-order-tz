<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\ProductMedia\ProductMediaDeleteSyncService;
use App\Services\ProductMedia\VariantMediaResolver;
use Database\Seeders\RoleSeeder;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class MediaOwnershipHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_admin_media_upload_creates_product_media_only(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = Product::factory()->create();

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('ownership.jpg'),
            'alt_text' => 'Owned by catalog',
            'is_primary' => true,
        ]);

        $response->assertCreated();
        $mediaId = $response->json('data.id');

        $this->assertDatabaseHas('product_media', [
            'id' => $mediaId,
            'product_id' => $product->id,
            'alt_text' => 'Owned by catalog',
        ]);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_legacy_images_endpoint_upload_also_writes_product_media_only(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = Product::factory()->create();

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/images', [
            'image' => MinimalTestImage::jpeg('legacy-endpoint.jpg'),
        ])->assertCreated();

        $this->assertSame('product_media', $response->json('data.source'));
        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseHas('product_media', [
            'id' => $response->json('data.id'),
            'product_id' => $product->id,
        ]);
        $this->assertTrue(Storage::disk('public')->exists($response->json('data.path')));
    }

    public function test_legacy_product_images_still_resolve_when_no_catalog_media(): void
    {
        $product = Product::factory()->fromChina()->create(['slug' => 'legacy-fallback-own']);
        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $legacy = ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
            'alt_text' => 'Legacy only',
        ]);

        $resolved = app(CustomerProductMediaResolver::class)->resolvePrimary($product);
        $this->assertNotNull($resolved);
        $this->assertSame($legacy->id, $resolved['id']);
    }

    public function test_variant_media_falls_back_to_product_media(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        $productMedia = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => 'https://cdn.example.com/product-level.jpg',
        ]);

        $resolved = app(VariantMediaResolver::class)->resolve($product, $variant);
        $this->assertCount(1, $resolved);
        $this->assertSame($productMedia->id, $resolved->first()->id);
    }

    public function test_deleted_media_soft_deletes_and_keeps_storage_file(): void
    {
        $product = Product::factory()->create();
        $path = 'products/owned-delete.jpg';
        Storage::disk('public')->put($path, 'image-bytes');

        $media = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url($path),
        ]);

        app(ProductMediaDeleteSyncService::class)->deleteFromCatalogMedia($media);

        $this->assertSoftDeleted('product_media', ['id' => $media->id]);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }
}
