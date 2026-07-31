<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\Inventory;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\ProductMedia\ProductImageWriteSyncService;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class CustomerProductMediaResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function createPurchasableProduct(array $overrides = []): Product
    {
        $product = Product::factory()->fromChina()->create($overrides);

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 10, 'reserved_quantity' => 0, 'low_stock_threshold' => 2],
        );

        return $product;
    }

    public function test_catalog_only_product_media_image_appears_on_pdp(): void
    {
        Storage::fake('public');

        $product = $this->createPurchasableProduct([
            'slug' => 'catalog-media-only',
        ]);

        $media = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/catalog-only.jpg',
            'alt_text' => 'Catalog only image',
        ]);

        $this->getJson('/api/v1/products/catalog-media-only')
            ->assertOk()
            ->assertJsonPath('data.primary_image.id', $media->id)
            ->assertJsonPath('data.primary_image.url', 'https://cdn.example.com/catalog-only.jpg')
            ->assertJsonPath('data.primary_image.alt_text', 'Catalog only image')
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.images.0.id', $media->id);
    }

    public function test_legacy_product_images_still_appear_when_no_catalog_media(): void
    {
        Storage::fake('public');

        $product = $this->createPurchasableProduct([
            'slug' => 'legacy-images-only',
        ]);

        $primary = ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
            'alt_text' => 'Legacy primary',
        ]);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('headphones.jpg'),
            'sort_order' => 2,
        ]);

        $this->getJson('/api/v1/products/legacy-images-only')
            ->assertOk()
            ->assertJsonPath('data.primary_image.id', $primary->id)
            ->assertJsonPath('data.primary_image.path', DemoProductImageLibrary::publicPath('phone.jpg'))
            ->assertJsonCount(2, 'data.images');
    }

    public function test_new_upload_creates_product_media_not_legacy_images(): void
    {
        Storage::fake('public');

        $product = $this->createPurchasableProduct([
            'slug' => 'catalog-upload-only',
        ]);

        $sync = app(ProductImageWriteSyncService::class)->storeUploadedImage(
            MinimalTestImage::jpeg('upload-only.jpg'),
            $product,
            [
                'alt_text' => 'Upload only image',
                'is_primary' => true,
                'sort_order' => 0,
            ],
        );

        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseHas('product_media', [
            'id' => $sync->catalogMedia->id,
            'product_id' => $product->id,
            'alt_text' => 'Upload only image',
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('headphones.jpg'),
            'is_primary' => false,
            'sort_order' => 99,
        ]);

        $this->getJson('/api/v1/products/catalog-upload-only')
            ->assertOk()
            ->assertJsonPath('data.primary_image.id', $sync->catalogMedia->id)
            ->assertJsonPath('data.primary_image.url', $sync->catalogMedia->url)
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.images.0.id', $sync->catalogMedia->id);
    }

    public function test_primary_image_selection_prefers_catalog_primary_flag(): void
    {
        $product = $this->createPurchasableProduct([
            'slug' => 'catalog-primary-priority',
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/secondary.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        $primary = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/primary.jpg',
            'sort_order' => 0,
        ]);

        $this->getJson('/api/v1/products/catalog-primary-priority')
            ->assertOk()
            ->assertJsonPath('data.primary_image.id', $primary->id)
            ->assertJsonPath('data.primary_image.url', 'https://cdn.example.com/primary.jpg');
    }

    public function test_missing_media_falls_back_to_null_primary_and_empty_gallery(): void
    {
        $product = $this->createPurchasableProduct([
            'slug' => 'no-media-product',
        ]);

        $this->getJson('/api/v1/products/no-media-product')
            ->assertOk()
            ->assertJsonPath('data.primary_image', null)
            ->assertJsonCount(0, 'data.images')
            ->assertJsonCount(0, 'data.videos');

        $resolver = app(CustomerProductMediaResolver::class);
        $this->assertNull($resolver->resolvePrimary($product));
        $this->assertSame([], $resolver->resolveGallery($product));
        $this->assertSame([], $resolver->resolveVideos($product));
    }

    public function test_product_list_card_uses_catalog_media_primary_image(): void
    {
        $product = $this->createPurchasableProduct([
            'name' => 'Listed Catalog Media Product',
        ]);

        $media = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/list-card.jpg',
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.primary_image.id', $media->id)
            ->assertJsonPath('data.0.primary_image.url', 'https://cdn.example.com/list-card.jpg');
    }

    public function test_active_video_appears_on_product_detail(): void
    {
        $product = $this->createPurchasableProduct([
            'slug' => 'product-with-video',
        ]);

        $video = ProductMedia::factory()->video()->create([
            'product_id' => $product->id,
            'title' => 'Demo walkthrough',
            'alt_text' => 'See it in action',
        ]);

        $this->getJson('/api/v1/products/product-with-video')
            ->assertOk()
            ->assertJsonPath('data.videos.0.id', $video->id)
            ->assertJsonPath('data.videos.0.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('data.videos.0.thumbnail_url', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->assertJsonPath('data.videos.0.title', 'Demo walkthrough')
            ->assertJsonPath('data.videos.0.alt_text', 'See it in action');
    }

    public function test_inactive_video_is_hidden_from_product_detail(): void
    {
        $product = $this->createPurchasableProduct([
            'slug' => 'product-with-inactive-video',
        ]);

        ProductMedia::factory()->video()->create([
            'product_id' => $product->id,
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/products/product-with-inactive-video')
            ->assertOk()
            ->assertJsonCount(0, 'data.videos');
    }

    public function test_images_remain_unchanged_when_videos_exist(): void
    {
        $product = $this->createPurchasableProduct([
            'slug' => 'product-with-image-and-video',
        ]);

        $image = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/hero.jpg',
            'alt_text' => 'Hero image',
        ]);
        ProductMedia::factory()->video()->create([
            'product_id' => $product->id,
        ]);

        $this->getJson('/api/v1/products/product-with-image-and-video')
            ->assertOk()
            ->assertJsonPath('data.primary_image.id', $image->id)
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.images.0.id', $image->id)
            ->assertJsonCount(1, 'data.videos');
    }

    public function test_product_without_videos_returns_empty_videos_array(): void
    {
        $this->createPurchasableProduct([
            'slug' => 'image-only-product',
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => Product::query()->where('slug', 'image-only-product')->value('id'),
            'url' => 'https://cdn.example.com/only-image.jpg',
        ]);

        $this->getJson('/api/v1/products/image-only-product')
            ->assertOk()
            ->assertJsonCount(1, 'data.images')
            ->assertJsonCount(0, 'data.videos');
    }

    public function test_tz_local_product_detail_exposes_videos_with_same_shape(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'slug' => 'tz-local-video-product',
        ]);

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $video = ProductMedia::factory()->video()->create([
            'product_id' => $product->id,
            'title' => 'TZ product video',
        ]);

        $this->getJson('/api/v1/products/tz-local-video-product')
            ->assertOk()
            ->assertJsonPath('data.videos.0.id', $video->id)
            ->assertJsonPath('data.videos.0.title', 'TZ product video');
    }
}
