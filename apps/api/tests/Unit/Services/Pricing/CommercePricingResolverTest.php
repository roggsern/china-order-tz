<?php

namespace Tests\Unit\Services\Pricing;

use App\Enums\PurchasabilityPath;
use App\Enums\VariantPriceType;
use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\ResolvePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2A-2B-1 / 2A-2B-2 — CommercePricingResolver (ADR 054).
 */
class CommercePricingResolverTest extends TestCase
{
    use RefreshDatabase;

    private CommercePricingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(CommercePricingResolver::class);
    }

    public function test_simple_product_uses_products_price(): void
    {
        $product = Product::factory()->create(['price' => 17500.5]);

        $result = $this->resolver->resolveSimpleProductPrice($product);

        $this->assertTrue($result->resolved);
        $this->assertSame(PurchasabilityPath::Simple, $result->path);
        $this->assertSame('products.price', $result->source);
        $this->assertSame('17500.50', $result->unitPrice);
        $this->assertSame('TZS', $result->currency);
    }

    public function test_variant_product_uses_variant_prices_retail(): void
    {
        $product = Product::factory()->create(['price' => 999]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 111,
            'is_active' => true,
        ]);
        $this->makeRetailPrice($variant, 22000);

        $result = $this->resolver->resolveVariantProductPrice(
            $variant->fresh(['prices']),
            new CommercePricingContext(currency: 'TZS'),
            $product,
        );

        $this->assertTrue($result->resolved);
        $this->assertSame(PurchasabilityPath::Variant, $result->path);
        $this->assertSame('variant_price_retail', $result->source);
        $this->assertSame('22000.00', $result->unitPrice);
    }

    public function test_variant_legacy_fallback_when_engine_missing(): void
    {
        $product = Product::factory()->create(['price' => 5000]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 8800,
            'is_active' => true,
        ]);

        $withLegacy = $this->resolver->resolveVariantProductPrice(
            $variant,
            new CommercePricingContext(allowLegacyVariantFallback: true),
            $product,
        );

        $this->assertTrue($withLegacy->resolved);
        $this->assertSame('legacy_variant_column', $withLegacy->source);
        $this->assertSame('8800.00', $withLegacy->unitPrice);

        $engineOnly = $this->resolver->resolveVariantProductPrice(
            $variant,
            new CommercePricingContext(allowLegacyVariantFallback: false),
            $product,
        );

        $this->assertFalse($engineOnly->resolved);
        $this->assertSame('variant_price_missing', $engineOnly->source);
    }

    public function test_resolve_unit_price_delegates_by_variant_argument(): void
    {
        $product = Product::factory()->create(['price' => 12000]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);
        $this->makeRetailPrice($variant, 15000);

        $simple = $this->resolver->resolveUnitPrice($product, null);
        $this->assertSame(PurchasabilityPath::Simple, $simple->path);
        $this->assertSame('12000.00', $simple->unitPrice);

        $varianted = $this->resolver->resolveUnitPrice($product, $variant->fresh(['prices']));
        $this->assertSame(PurchasabilityPath::Variant, $varianted->path);
        $this->assertSame('15000.00', $varianted->unitPrice);
    }

    public function test_resolve_price_base_stage_delegates_to_commerce_pricing_resolver(): void
    {
        $product = Product::factory()->create(['price' => 64000]);

        $breakdown = app(ResolvePrice::class)->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: null,
            quantity: 2,
        ));

        $this->assertSame('64000.00', $breakdown->unitPrice);
        $this->assertSame('128000.00', $breakdown->lineTotal);
        $this->assertSame('base', $breakdown->stages[0]->stage);
        $this->assertSame('products.price', $breakdown->stages[0]->meta['source'] ?? null);
    }

    public function test_reserved_extensions_are_no_ops(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $current = $this->resolver->resolveSimpleProductPrice($product);
        $context = new CommercePricingContext(
            quantity: 10,
            customerId: 'customer-1',
            channel: 'pos',
            region: 'dar',
        );

        $afterCustomer = $this->resolver->applyCustomerPricingExtension($current, $context);
        $afterChannel = $this->resolver->applyChannelPricingExtension($afterCustomer, $context);
        $afterRegion = $this->resolver->applyRegionalPricingExtension($afterChannel, $context);

        $this->assertSame($current->unitPrice, $afterRegion->unitPrice);
        $this->assertSame($current->source, $afterRegion->source);
    }

    public function test_variant_product_full_pipeline_retail(): void
    {
        $product = Product::factory()->create(['price' => 10000]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 99999,
            'is_active' => true,
        ]);
        $this->makeRetailPrice($variant, 42000);

        $result = $this->resolver->resolveCommerceUnitPrice(
            $product,
            $variant->fresh(['prices']),
            new CommercePricingContext(quantity: 1, allowLegacyVariantFallback: false),
        );

        $this->assertTrue($result->resolved);
        $this->assertSame('42000.00', $result->unitPrice);
        $this->assertSame('variant_price_retail', $result->source);
        $this->assertSame(PurchasabilityPath::Variant, $result->path);
    }

    public function test_moq_applies_identically_for_simple_and_variant_paths(): void
    {
        $product = Product::factory()->create(['price' => 10000]);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'unit_price' => 8500,
        ]);

        $simple = $this->resolver->resolveCommerceUnitPrice(
            $product,
            null,
            new CommercePricingContext(quantity: 10),
        );

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);
        $this->makeRetailPrice($variant, 12000);

        $configured = $this->resolver->resolveCommerceUnitPrice(
            $product,
            $variant->fresh(['prices']),
            new CommercePricingContext(quantity: 10),
        );

        $this->assertSame('8500.00', $simple->unitPrice);
        $this->assertTrue($simple->meta['moq_applied']);
        $this->assertSame('8500.00', $configured->unitPrice);
        $this->assertTrue($configured->meta['moq_applied']);
    }

    public function test_resolve_unit_price_aliases_commerce_pipeline(): void
    {
        $product = Product::factory()->create(['price' => 10000]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);
        $this->makeRetailPrice($variant, 20000);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 5,
            'unit_price' => 9000,
        ]);

        $context = new CommercePricingContext(quantity: 5);

        $viaAlias = $this->resolver->resolveUnitPrice(
            $product,
            $variant->fresh(['prices']),
            $context,
        );
        $viaCanonical = $this->resolver->resolveCommerceUnitPrice(
            $product,
            $variant->fresh(['prices']),
            $context,
        );

        $this->assertSame($viaCanonical->unitPrice, $viaAlias->unitPrice);
        $this->assertSame('9000.00', $viaAlias->unitPrice);
        $this->assertTrue($viaAlias->meta['moq_applied']);
    }

    private function makeRetailPrice(ProductVariant $variant, float $amount): VariantPrice
    {
        return VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $amount,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
    }
}
