<?php

namespace Tests\Unit\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Services\Catalog\CustomerProductMediaResolver;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerProductMediaResolverTest extends TestCase
{
    use RefreshDatabase;

    private CustomerProductMediaResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(CustomerProductMediaResolver::class);
    }

    public function test_resolve_primary_prefers_active_catalog_media(): void
    {
        $product = Product::factory()->create();

        ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
        ]);

        $media = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/catalog-primary.jpg',
        ]);

        $primary = $this->resolver->resolvePrimary($product->fresh(['media', 'images']));

        $this->assertSame($media->id, $primary['id']);
        $this->assertSame('https://cdn.example.com/catalog-primary.jpg', $primary['url']);
    }

    public function test_resolve_gallery_falls_back_to_legacy_images_when_catalog_media_missing(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
        ]);

        $gallery = $this->resolver->resolveGallery($product->fresh(['media', 'images']));

        $this->assertCount(1, $gallery);
        $this->assertSame(DemoProductImageLibrary::publicPath('phone.jpg'), $gallery[0]['path']);
    }

    public function test_resolve_admin_gallery_prefers_catalog_media_and_preserves_primary(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();

        ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
            'alt_text' => 'Legacy image',
        ]);

        $primary = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url(DemoProductImageLibrary::publicPath('shoes.jpg')),
            'thumbnail_url' => Storage::disk('public')->url(DemoProductImageLibrary::publicPath('shoes.jpg')),
            'alt_text' => 'Catalog primary',
            'sort_order' => 0,
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url(DemoProductImageLibrary::publicPath('watch.jpg')),
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $gallery = $this->resolver->resolveAdminGallery($product->fresh(['media', 'images']));

        $this->assertCount(2, $gallery);
        $primaryRow = collect($gallery)->firstWhere('is_primary', true);
        $this->assertNotNull($primaryRow);
        $this->assertSame($primary->id, $primaryRow['id']);
        $this->assertSame('Catalog primary', $primaryRow['alt_text']);
        $this->assertNotNull($primaryRow['url']);
        $this->assertNotNull($primaryRow['thumbnail_url']);
    }

    public function test_resolve_admin_gallery_returns_empty_array_when_no_media_exists(): void
    {
        $product = Product::factory()->create();

        $gallery = $this->resolver->resolveAdminGallery($product->fresh(['media', 'images']));

        $this->assertSame([], $gallery);
    }

    public function test_resolve_videos_returns_active_catalog_videos(): void
    {
        $product = Product::factory()->create();

        $video = ProductMedia::factory()->video()->create([
            'product_id' => $product->id,
            'title' => 'Product walkthrough',
            'sort_order' => 1,
        ]);

        $videos = $this->resolver->resolveVideos($product->fresh(['videos']));

        $this->assertCount(1, $videos);
        $this->assertSame($video->id, $videos[0]['id']);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $videos[0]['url']);
        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $videos[0]['thumbnail_url']);
        $this->assertSame('Product walkthrough', $videos[0]['title']);
        $this->assertSame(1, $videos[0]['sort_order']);
    }

    public function test_resolve_videos_excludes_inactive_catalog_videos(): void
    {
        $product = Product::factory()->create();

        ProductMedia::factory()->video()->create([
            'product_id' => $product->id,
            'is_active' => false,
        ]);

        $this->assertSame([], $this->resolver->resolveVideos($product->fresh(['videos'])));
    }

    public function test_resolve_gallery_excludes_videos(): void
    {
        $product = Product::factory()->create();

        $image = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/primary.jpg',
        ]);
        ProductMedia::factory()->video()->create([
            'product_id' => $product->id,
        ]);

        $gallery = $this->resolver->resolveGallery($product->fresh(['media', 'images', 'videos']));

        $this->assertCount(1, $gallery);
        $this->assertSame($image->id, $gallery[0]['id']);
    }
}
