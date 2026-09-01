<?php

namespace Tests\Unit\Services\Purchasing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Purchasing\PresentPurchaseQuantity;
use App\Services\Purchasing\PurchaseQuantityRule;
use Tests\TestCase;

class PresentPurchaseQuantityTest extends TestCase
{
    public function test_no_restriction_presents_null(): void
    {
        $product = $this->product(null, null);

        $this->assertNull(app(PresentPurchaseQuantity::class)->present($product, 4));
    }

    public function test_uses_rule_evaluation_not_independent_arithmetic(): void
    {
        $product = $this->product(6, 3);
        $presented = app(PresentPurchaseQuantity::class)->present($product, 7);
        $evaluation = PurchaseQuantityRule::forProduct($product)->evaluate(7);

        $this->assertNotNull($presented);
        $this->assertSame($evaluation->minimumSatisfied, $presented->minimumSatisfied);
        $this->assertSame($evaluation->incrementSatisfied, $presented->incrementSatisfied);
        $this->assertSame($evaluation->isLegal, $presented->constructionComplete);
        $this->assertSame(! $evaluation->isLegal, $presented->blocksCheckout);
        $this->assertSame($evaluation->nextLegalQuantity, $presented->nextLegalQuantity);
        $this->assertSame(9, $presented->nextLegalQuantity);
    }

    public function test_increment_without_moq_presents_effective_minimum(): void
    {
        $product = $this->product(null, 3);
        $presented = app(PresentPurchaseQuantity::class)->present($product, 1);

        $this->assertNotNull($presented);
        $this->assertSame(3, $presented->minimumQuantity);
        $this->assertSame(3, $presented->increment);
        $this->assertFalse($presented->constructionComplete);
        $this->assertTrue($presented->blocksCheckout);
    }

    public function test_zero_stored_values_present_as_unrestricted(): void
    {
        $product = $this->product(0, 0);

        $this->assertNull(app(PresentPurchaseQuantity::class)->present($product, 1));
    }

    public function test_inactive_variants_do_not_enable_aggregates_variants(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'minimum_order_quantity' => 6,
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => false]);
        $trashed = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        $trashed->delete();

        $presented = app(PresentPurchaseQuantity::class)->present($product->fresh(), 2);

        $this->assertFalse($presented?->aggregatesVariants);
    }

    public function test_aggregates_variants_is_capability_not_current_line_count(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'minimum_order_quantity' => 6,
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);

        $presented = app(PresentPurchaseQuantity::class)->present($product->fresh(), 2);

        $this->assertTrue($presented?->aggregatesVariants);
    }

    private function product(?int $minimum, ?int $increment): Product
    {
        $product = new Product;
        $product->id = '019fd0f5-c8d8-718e-b46d-180309aeb88c';
        $product->minimum_order_quantity = $minimum;
        $product->order_increment = $increment;
        $product->setRelation('variants', collect());

        return $product;
    }
}
