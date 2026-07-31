<?php

namespace Tests\Unit\Services\ProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\ProductMedia\VariantMediaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantMediaResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_variant_media_when_present(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black / Small',
            'is_active' => true,
        ]);

        $productMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/product.jpg',
            'sort_order' => 0,
        ]);
        $variantMedia = ProductMedia::factory()->forVariant($variant)->create([
            'product_id' => $product->id,
            'url' => '/storage/variant.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $resolved = app(VariantMediaResolver::class)->resolve($product, $variant);

        $this->assertCount(1, $resolved);
        $this->assertTrue($resolved->contains(fn (ProductMedia $media) => $media->id === $variantMedia->id));
        $this->assertFalse($resolved->contains(fn (ProductMedia $media) => $media->id === $productMedia->id));
    }

    public function test_falls_back_to_product_media_when_variant_has_none(): void
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

        $resolved = app(VariantMediaResolver::class)->resolve($product, $variant);

        $this->assertCount(1, $resolved);
        $this->assertSame($productMedia->id, $resolved->first()->id);
    }

    public function test_product_without_variant_returns_product_media(): void
    {
        $product = Product::factory()->create();
        $productMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
        ]);

        $resolved = app(VariantMediaResolver::class)->resolve($product, null);

        $this->assertCount(1, $resolved);
        $this->assertSame($productMedia->id, $resolved->first()->id);
    }
}
