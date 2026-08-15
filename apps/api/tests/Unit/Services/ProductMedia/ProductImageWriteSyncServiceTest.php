<?php

namespace Tests\Unit\Services\ProductMedia;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Services\ProductMedia\ProductImageWriteSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class ProductImageWriteSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_uploaded_image_creates_product_media_only(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $service = app(ProductImageWriteSyncService::class);

        $file = extension_loaded('gd')
            ? \Illuminate\Http\UploadedFile::fake()->image('catalog.jpg', 400, 300)
            : MinimalTestImage::jpeg('catalog.jpg');

        $result = $service->storeUploadedImage(
            $file,
            $product,
            [
                'alt_text' => 'Catalog image',
                'title' => 'Catalog title',
                'sort_order' => 2,
                'is_primary' => true,
            ],
        );

        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame(1, ProductMedia::query()->count());
        $this->assertNull($result->legacyImage);

        $media = $result->catalogMedia;
        $this->assertSame($product->id, $media->product_id);
        $this->assertNull($media->product_variant_id);
        $this->assertSame('Catalog image', $media->alt_text);
        $this->assertSame(2, $media->sort_order);
        $this->assertTrue($media->is_primary);
        $this->assertNotNull($result->storagePath);
        $this->assertTrue(Storage::disk('public')->exists($result->storagePath));
        $this->assertSame(Storage::disk('public')->url($result->storagePath), $media->url);

        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $this->assertNotNull($media->display_url);
            $stem = pathinfo($result->storagePath, PATHINFO_FILENAME);
            $this->assertTrue(
                Storage::disk('public')->exists('products/storefront/'.$stem.'.webp'),
            );
        }
    }

    public function test_variant_upload_writes_catalog_media_only(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $variant = \App\Models\ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'MEDIA-VAR-1',
            'name' => 'Variant',
            'is_active' => true,
            'is_default' => true,
        ]);

        $result = app(ProductImageWriteSyncService::class)->storeUploadedImage(
            MinimalTestImage::jpeg('variant.jpg'),
            $product,
            [
                'product_variant_id' => $variant->id,
                'is_primary' => true,
            ],
        );

        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame(1, ProductMedia::query()->count());
        $this->assertSame($variant->id, $result->catalogMedia->product_variant_id);
    }

    public function test_store_uploaded_image_rolls_back_when_media_create_fails(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $service = app(ProductImageWriteSyncService::class);

        ProductMedia::creating(function (): void {
            throw new RuntimeException('Forced media create failure.');
        });

        try {
            $service->storeUploadedImage(
                MinimalTestImage::jpeg('rollback.jpg'),
                $product,
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced media create failure.', $exception->getMessage());
        } finally {
            ProductMedia::clearBootedModels();
            ProductImage::clearBootedModels();
        }

        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame(0, ProductMedia::query()->count());
    }
}
