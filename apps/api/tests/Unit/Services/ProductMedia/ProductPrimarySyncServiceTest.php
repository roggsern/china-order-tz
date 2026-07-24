<?php

namespace Tests\Unit\Services\ProductMedia;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Services\ProductMedia\ProductPrimarySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductPrimarySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_set_primary_updates_product_media_and_product_images(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $path = 'products/catalog-primary.jpg';
        Storage::disk('public')->put($path, 'image');

        $legacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => false,
            'sort_order' => 0,
        ]);
        $otherLegacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/other.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        Storage::disk('public')->put('products/other.jpg', 'image');

        $targetMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url($path),
            'is_primary' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $otherMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url('products/other.jpg'),
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = app(ProductPrimarySyncService::class);
        $result = $service->setPrimaryFromCatalogMedia($targetMedia);

        $this->assertTrue($result->is_primary);
        $this->assertTrue($legacy->fresh()->is_primary);
        $this->assertFalse($otherLegacy->fresh()->is_primary);
        $this->assertFalse($otherMedia->fresh()->is_primary);
    }

    public function test_legacy_set_primary_updates_product_images_and_product_media(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $path = 'products/legacy-primary.jpg';
        Storage::disk('public')->put($path, 'image');

        $targetLegacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => false,
            'sort_order' => 0,
        ]);
        $otherLegacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/other-legacy.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        Storage::disk('public')->put('products/other-legacy.jpg', 'image');

        $targetMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url($path),
            'is_primary' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $otherMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url('products/other-legacy.jpg'),
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = app(ProductPrimarySyncService::class);
        $result = $service->setPrimaryFromLegacyImage($targetLegacy);

        $this->assertTrue($result->is_primary);
        $this->assertTrue($targetMedia->fresh()->is_primary);
        $this->assertFalse($otherLegacy->fresh()->is_primary);
        $this->assertFalse($otherMedia->fresh()->is_primary);
    }

    public function test_unpaired_url_only_catalog_media_updates_catalog_table_only(): void
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

        $unpairedMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => '/storage/demo-products/external.jpg',
            'is_primary' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $service = app(ProductPrimarySyncService::class);
        $result = $service->setPrimaryFromCatalogMedia($unpairedMedia);

        $this->assertTrue($result->is_primary);
        $this->assertFalse($legacy->fresh()->is_primary);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, ProductMedia::query()->where('product_id', $product->id)->count());
    }

    public function test_primary_sync_rolls_back_when_paired_update_fails(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $path = 'products/rollback-primary.jpg';
        Storage::disk('public')->put($path, 'image');

        $legacy = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => false,
            'sort_order' => 0,
        ]);
        $previousPrimary = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/previous-primary.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        Storage::disk('public')->put('products/previous-primary.jpg', 'image');

        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => Storage::disk('public')->url($path),
            'is_primary' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ProductImage::updating(function (): void {
            throw new RuntimeException('Forced legacy primary update failure.');
        });

        try {
            app(ProductPrimarySyncService::class)->setPrimaryFromCatalogMedia($media);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced legacy primary update failure.', $exception->getMessage());
        } finally {
            ProductImage::clearBootedModels();
            ProductMedia::clearBootedModels();
        }

        $this->assertFalse($legacy->fresh()->is_primary);
        $this->assertTrue($previousPrimary->fresh()->is_primary);
        $this->assertFalse($media->fresh()->is_primary);
    }
}
