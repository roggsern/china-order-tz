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

    public function test_store_uploaded_image_creates_both_rows_with_one_file(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $service = app(ProductImageWriteSyncService::class);

        $result = $service->storeUploadedImage(
            MinimalTestImage::jpeg('catalog.jpg'),
            $product,
            [
                'alt_text' => 'Synced image',
                'title' => 'Catalog title',
                'sort_order' => 2,
                'is_primary' => true,
            ],
        );

        $this->assertSame(1, ProductImage::query()->count());
        $this->assertSame(1, ProductMedia::query()->count());

        $legacy = $result->legacyImage;
        $this->assertSame($product->id, $legacy->product_id);
        $this->assertSame('Synced image', $legacy->alt_text);
        $this->assertSame(2, $legacy->sort_order);
        $this->assertTrue($legacy->is_primary);
        $this->assertTrue(Storage::disk('public')->exists($legacy->path));

        $media = $result->catalogMedia;
        $this->assertSame($media->id, ProductMedia::query()->value('id'));
        $this->assertSame(Storage::disk('public')->url($legacy->path), $media->url);
        $this->assertTrue($media->is_primary);
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
