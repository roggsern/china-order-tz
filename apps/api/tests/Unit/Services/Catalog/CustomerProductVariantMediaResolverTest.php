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
        $this->assertSame('/storage/variant.jpg', $primary['original_url']);
        $this->assertNull($primary['display_url']);
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

    public function test_resolve_gallery_orders_variant_media_primary_first_then_sort_order_when_shuffled(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $ordered = [];
        for ($sortOrder = 0; $sortOrder < 6; $sortOrder++) {
            $ordered[] = ProductMedia::factory()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'url' => '/storage/variant-'.$sortOrder.'.jpg',
                'sort_order' => $sortOrder,
                'is_primary' => $sortOrder === 0,
            ]);
        }

        $variant->load(CustomerProductMediaResolver::variantMediaEagerLoads());
        $variant->setRelation('media', $variant->media->shuffle()->values());

        $gallery = app(CustomerProductMediaResolver::class)->resolveGallery(
            $product->fresh(['media']),
            $variant,
        );

        $this->assertSame(
            array_map(static fn (ProductMedia $media) => $media->id, $ordered),
            array_column($gallery, 'id'),
        );
        $this->assertSame($ordered[0]->id, $gallery[0]['id']);
    }

    public function test_variant_gallery_exposes_display_url_when_present(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'url' => '/storage/products/variant-original.jpg',
            'display_url' => '/storage/products/storefront/variant-original.webp',
        ]);

        $gallery = app(CustomerProductMediaResolver::class)->resolveGallery(
            $product->fresh(['media']),
            $variant->fresh(['media']),
        );

        $this->assertCount(1, $gallery);
        $this->assertSame('/storage/products/variant-original.jpg', $gallery[0]['url']);
        $this->assertSame('/storage/products/variant-original.jpg', $gallery[0]['original_url']);
        $this->assertSame('/storage/products/storefront/variant-original.webp', $gallery[0]['display_url']);
    }
}
