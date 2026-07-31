<?php

namespace Tests\Unit\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Catalog\CustomerProductMediaResolver;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProductVariantMediaResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_primary_prefers_variant_media_via_variant_media_resolver(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/product.jpg',
        ]);
        $variantMedia = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'url' => '/storage/variant.jpg',
        ]);

        $primary = app(CustomerProductMediaResolver::class)->resolvePrimary(
            $product->fresh(['media']),
            $variant->fresh(['media']),
        );

        $this->assertSame($variantMedia->id, $primary['id']);
        $this->assertSame('/storage/variant.jpg', $primary['url']);
    }

    public function test_resolve_gallery_falls_back_to_product_media_when_variant_has_none(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $productMedia = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/product-fallback.jpg',
        ]);

        $gallery = app(CustomerProductMediaResolver::class)->resolveGallery(
            $product->fresh(['media']),
            $variant->fresh(['media']),
        );

        $this->assertCount(1, $gallery);
        $this->assertSame($productMedia->id, $gallery[0]['id']);
        $this->assertSame('/storage/product-fallback.jpg', $gallery[0]['url']);
    }

    public function test_resolve_primary_falls_back_to_legacy_product_images_when_no_catalog_media(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $legacy = ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
            'alt_text' => 'Legacy fallback',
        ]);

        $primary = app(CustomerProductMediaResolver::class)->resolvePrimary(
            $product->fresh(['media', 'images']),
            $variant->fresh(['media']),
        );

        $this->assertNotNull($primary);
        $this->assertSame($legacy->id, $primary['id']);
        $this->assertSame(DemoProductImageLibrary::publicPath('phone.jpg'), $primary['path']);
    }
}
