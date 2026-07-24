<?php

namespace Tests\Unit\Services\ProductMedia;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Services\ProductMedia\ProductMediaDeleteSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductMediaDeleteSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_delete_soft_deletes_product_media_and_paired_product_images(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $path = 'products/catalog-delete.jpg';
        Storage::disk('public')->put($path, 'image');

        $legacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url($path),
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        app(ProductMediaDeleteSyncService::class)->deleteFromCatalogMedia($media);

        $this->assertSoftDeleted('product_images', ['id' => $legacy->id]);
        $this->assertSoftDeleted('product_media', ['id' => $media->id]);
        $this->assertFalse($legacy->fresh()->is_primary);
        $this->assertFalse($media->fresh()->is_primary);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_legacy_delete_soft_deletes_product_images_and_paired_product_media(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $path = 'products/legacy-delete.jpg';
        Storage::disk('public')->put($path, 'image');

        $legacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url($path),
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        app(ProductMediaDeleteSyncService::class)->deleteFromLegacyImage($legacy);

        $this->assertSoftDeleted('product_images', ['id' => $legacy->id]);
        $this->assertSoftDeleted('product_media', ['id' => $media->id]);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_url_only_catalog_media_deletes_catalog_row_only(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $legacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/legacy-only.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        Storage::disk('public')->put('products/legacy-only.jpg', 'image');

        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => '/storage/demo-products/external.jpg',
            'is_primary' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        app(ProductMediaDeleteSyncService::class)->deleteFromCatalogMedia($media);

        $this->assertSoftDeleted('product_media', ['id' => $media->id]);
        $this->assertDatabaseHas('product_images', [
            'id' => $legacy->id,
            'deleted_at' => null,
        ]);
    }

    public function test_unpaired_legacy_delete_soft_deletes_legacy_row_only(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $path = 'products/unpaired-legacy.jpg';
        Storage::disk('public')->put($path, 'image');

        $legacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => false,
            'sort_order' => 0,
        ]);

        app(ProductMediaDeleteSyncService::class)->deleteFromLegacyImage($legacy);

        $this->assertSoftDeleted('product_images', ['id' => $legacy->id]);
        $this->assertSame(0, ProductMedia::query()->count());
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_delete_sync_rolls_back_when_paired_legacy_delete_fails(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $path = 'products/delete-rollback.jpg';
        Storage::disk('public')->put($path, 'image');

        $legacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url($path),
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ProductImage::deleting(function (): void {
            throw new RuntimeException('Forced legacy delete failure.');
        });

        try {
            app(ProductMediaDeleteSyncService::class)->deleteFromCatalogMedia($media);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced legacy delete failure.', $exception->getMessage());
        } finally {
            ProductImage::clearBootedModels();
            ProductMedia::clearBootedModels();
        }

        $this->assertDatabaseHas('product_images', [
            'id' => $legacy->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('product_media', [
            'id' => $media->id,
            'deleted_at' => null,
        ]);
        $this->assertTrue($legacy->fresh()->is_primary);
        $this->assertTrue($media->fresh()->is_primary);
    }
}
