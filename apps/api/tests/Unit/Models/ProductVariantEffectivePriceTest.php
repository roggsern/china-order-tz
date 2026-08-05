<?php

namespace Tests\Unit\Models;

use App\Enums\VariantPriceType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantEffectivePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_retail_price_when_present(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 999,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 1500,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $this->assertSame('1500.00', $variant->fresh()->effectivePrice());
    }

    public function test_returns_null_when_no_price_sources_exist(): void
    {
        $ghost = new ProductVariant([
            'price' => null,
            'product_id' => '00000000-0000-0000-0000-000000000000',
        ]);
        $ghost->setRelation('product', null);
        $ghost->setRelation('prices', collect());

        $this->assertNull($ghost->effectivePrice());
    }

    public function test_soft_deleted_parent_does_not_throw(): void
    {
        $product = Product::factory()->create(['price' => 22000]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
        ]);
        $product->delete();

        $variant->refresh()->load('product');
        $this->assertNull($variant->product);
        $this->assertSame('22000.00', $variant->effectivePrice());
    }
}
