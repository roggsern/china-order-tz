<?php

namespace Tests\Unit\Services\Pricing;

use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\ResolvePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolvePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_applies_base_configuration_override_and_quantity_tier(): void
    {
        $type = ProductType::query()->create([
            'name' => 'Phones',
            'slug' => 'phones-pricing',
            'has_configurations' => true,
            'allows_price_override' => true,
            'allows_moq_pricing' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'product_type_id' => $type->id,
            'price' => 100000,
            'sku' => 'PHONE-BASE',
        ]);

        $silver128 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Silver / 128GB',
            'sku' => 'PHONE-SILVER-128',
            'price' => 110000,
        ]);

        $silver512 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Silver / 512GB',
            'sku' => 'PHONE-SILVER-512',
            'price' => 150000,
        ]);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $silver128->id,
            'min_quantity' => 1,
            'unit_price' => 110000,
        ]);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $silver128->id,
            'min_quantity' => 10,
            'unit_price' => 100000,
        ]);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $silver128->id,
            'min_quantity' => 50,
            'unit_price' => 90000,
        ]);

        $resolver = app(ResolvePrice::class);

        $qty1 = $resolver->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $silver128->id,
            quantity: 1,
        ));

        $this->assertSame('110000.00', $qty1->unitPrice);
        $this->assertSame('110000.00', $qty1->lineTotal);
        $this->assertSame('base', $qty1->stages[0]->stage);
        $this->assertTrue($qty1->stages[0]->applied);
        $this->assertSame('100000.00', $qty1->stages[0]->unitPrice);
        $this->assertTrue($qty1->stages[1]->applied);
        $this->assertSame('110000.00', $qty1->stages[1]->unitPrice);
        $this->assertTrue($qty1->stages[2]->applied);
        $this->assertFalse($qty1->stages[3]->applied); // promotion reserved
        $this->assertFalse($qty1->stages[4]->applied); // coupon reserved
        $this->assertFalse($qty1->stages[5]->applied); // customer group reserved
        $this->assertSame('final', $qty1->stages[6]->stage);

        $qty10 = $resolver->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $silver128->id,
            quantity: 10,
        ));
        $this->assertSame('100000.00', $qty10->unitPrice);
        $this->assertSame('1000000.00', $qty10->lineTotal);

        $qty50 = $resolver->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $silver128->id,
            quantity: 50,
        ));
        $this->assertSame('90000.00', $qty50->unitPrice);
        $this->assertSame('4500000.00', $qty50->lineTotal);

        $otherConfig = $resolver->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $silver512->id,
            quantity: 1,
        ));
        $this->assertSame('150000.00', $otherConfig->unitPrice);
    }

    public function test_falls_back_to_product_level_tiers_when_configuration_has_none(): void
    {
        $type = ProductType::query()->create([
            'name' => 'TVs',
            'slug' => 'tvs-pricing',
            'has_configurations' => true,
            'allows_price_override' => true,
            'allows_moq_pricing' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'product_type_id' => $type->id,
            'price' => 800000,
        ]);

        $tv75 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => '75 inch',
            'price' => 950000,
        ]);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 5,
            'unit_price' => 900000,
        ]);

        $quote = app(ResolvePrice::class)->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $tv75->id,
            quantity: 5,
        ));

        $this->assertSame('900000.00', $quote->unitPrice);
        $this->assertTrue($quote->stages[2]->applied);
        $this->assertSame('product', $quote->stages[2]->meta['scope']);
    }

    public function test_percent_off_quantity_tier_applies_discount_from_pipeline_price(): void
    {
        $type = ProductType::query()->create([
            'name' => 'Accessories',
            'slug' => 'accessories-pricing',
            'has_configurations' => false,
            'allows_price_override' => false,
            'allows_moq_pricing' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'product_type_id' => $type->id,
            'price' => 100000,
            'sku' => 'ACC-BASE',
        ]);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'tier_type' => 'percent_off',
            'unit_price' => 0,
            'discount_percent' => 10,
        ]);

        $quote = app(ResolvePrice::class)->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: null,
            quantity: 10,
        ));

        $this->assertSame('90000.00', $quote->unitPrice);
        $this->assertTrue($quote->stages[2]->applied);
        $this->assertSame('percent_off', $quote->stages[2]->meta['tier_type']);
    }
}
