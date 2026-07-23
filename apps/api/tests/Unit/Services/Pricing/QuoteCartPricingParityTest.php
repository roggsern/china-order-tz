<?php

namespace Tests\Unit\Services\Pricing;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\ConfigurationPriceTier;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Cart\ResolveCartPurchasable;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\ResolvePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2A-2B-2 — Quote unit price must equal Cart unit price via CommercePricingResolver (ADR 054).
 */
class QuoteCartPricingParityTest extends TestCase
{
    use RefreshDatabase;

    private ResolvePrice $resolvePrice;

    private ResolveCartPurchasable $resolveCart;

    private CommercePricingResolver $commercePricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolvePrice = app(ResolvePrice::class);
        $this->resolveCart = app(ResolveCartPurchasable::class);
        $this->commercePricing = app(CommercePricingResolver::class);
    }

    public function test_simple_product_quote_equals_cart_unit_price(): void
    {
        $product = $this->makeSimpleProduct(['price' => 17500], stock: 20);

        $quote = $this->resolvePrice->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: null,
            quantity: 2,
        ));
        $cart = $this->resolveCart->handle($product->id, null, 2);

        $this->assertSame($quote->unitPrice, $cart['unit_price']);
        $this->assertSame('17500.00', $quote->unitPrice);
    }

    public function test_variant_product_with_engine_retail_quote_equals_cart(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->makeVariantProduct(45000, stock: 10);

        $quote = $this->resolvePrice->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $variant->id,
            quantity: 1,
        ));
        $cart = $this->resolveCart->handle($product->id, $variant->id, 1);

        $this->assertSame($quote->unitPrice, $cart['unit_price']);
        $this->assertSame('45000.00', $quote->unitPrice);
    }

    public function test_moq_parity_quote_equals_cart(): void
    {
        $product = $this->makeSimpleProduct(['price' => 10000], stock: 50);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'unit_price' => 8000,
        ]);

        $quoteBelow = $this->resolvePrice->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: null,
            quantity: 5,
        ));
        $cartBelow = $this->resolveCart->handle($product->id, null, 5);
        $this->assertSame($quoteBelow->unitPrice, $cartBelow['unit_price']);
        $this->assertSame('10000.00', $quoteBelow->unitPrice);

        $quoteAtMoq = $this->resolvePrice->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: null,
            quantity: 10,
        ));
        $cartAtMoq = $this->resolveCart->handle($product->id, null, 10);
        $this->assertSame($quoteAtMoq->unitPrice, $cartAtMoq['unit_price']);
        $this->assertSame('8000.00', $quoteAtMoq->unitPrice);
    }

    public function test_configuration_legacy_override_parity_via_shared_resolver(): void
    {
        $product = $this->makeSimpleProduct(['price' => 12000], stock: 20);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 15500,
            'is_active' => true,
        ]);

        $quote = $this->resolvePrice->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $variant->id,
            quantity: 1,
        ));

        $cartContext = $this->commercePricing->resolveCommerceUnitPrice(
            $product,
            $variant,
            new CommercePricingContext(
                currency: 'TZS',
                quantity: 1,
                allowLegacyVariantFallback: true,
            ),
        );

        $this->assertSame($quote->unitPrice, $cartContext->unitPrice);
        $this->assertSame('15500.00', $quote->unitPrice);
    }

    public function test_configuration_plus_moq_parity(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->makeVariantProduct(22000, stock: 20);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 5,
            'unit_price' => 7000,
        ]);

        $quote = $this->resolvePrice->handle($product, new PriceQuoteInput(
            productId: $product->id,
            configurationId: $variant->id,
            quantity: 5,
        ));
        $cart = $this->resolveCart->handle($product->id, $variant->id, 5);

        $this->assertSame($quote->unitPrice, $cart['unit_price']);
        $this->assertSame('7000.00', $quote->unitPrice);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSimpleProduct(array $overrides = [], int $stock = 10): Product
    {
        $product = Product::factory()->create(array_merge([
            'price' => 10000,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
        ], $overrides));

        Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => $stock,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh(['inventory', 'variants']) ?? $product;
    }

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function makeVariantProduct(float $retail, int $stock = 10): array
    {
        $product = Product::factory()->create([
            'price' => 0,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => null,
            'is_active' => true,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $retail,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $stock,
            'reserved' => 0,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        return [
            'product' => $product->fresh(['variants.prices', 'variants.inventories']) ?? $product,
            'variant' => $variant->fresh(['prices', 'inventories']) ?? $variant,
        ];
    }
}
