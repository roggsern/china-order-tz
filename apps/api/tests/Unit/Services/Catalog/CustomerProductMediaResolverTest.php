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
}
