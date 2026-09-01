<?php

namespace Tests\Unit\Services\Purchasing;

use App\Models\Product;
use App\Services\Purchasing\PurchaseQuantityEvaluation;
use App\Services\Purchasing\PurchaseQuantityRule;
use Tests\TestCase;

class PurchaseQuantityRuleTest extends TestCase
{
    public function test_null_null_is_unrestricted(): void
    {
        $evaluation = $this->evaluate(null, null, 1);

        $this->assertTrue($evaluation->isLegal);
        $this->assertFalse($evaluation->hasRestriction);
        $this->assertTrue($evaluation->minimumSatisfied);
        $this->assertTrue($evaluation->incrementSatisfied);
        $this->assertNull($evaluation->minimumQuantity);
        $this->assertNull($evaluation->increment);
    }

    public function test_moq_without_increment_allows_any_integer_at_or_above_minimum(): void
    {
        $this->assertFalse($this->evaluate(6, null, 5)->isLegal);
        $this->assertTrue($this->evaluate(6, null, 6)->isLegal);
        $this->assertTrue($this->evaluate(6, null, 7)->isLegal);
        $this->assertTrue($this->evaluate(6, null, 10)->isLegal);
        $this->assertSame(1, $this->evaluate(6, null, 5)->quantityToMinimum);
        $this->assertSame(6, $this->evaluate(6, null, 5)->nextLegalQuantity);
    }

    public function test_moq_with_increment_uses_offset_from_minimum(): void
    {
        $this->assertTrue($this->evaluate(6, 3, 6)->isLegal);
        $this->assertTrue($this->evaluate(6, 3, 9)->isLegal);
        $this->assertTrue($this->evaluate(6, 3, 12)->isLegal);
        $this->assertFalse($this->evaluate(6, 3, 7)->isLegal);
        $this->assertFalse($this->evaluate(6, 3, 8)->isLegal);
        $this->assertFalse($this->evaluate(6, 3, 10)->isLegal);
        $this->assertFalse($this->evaluate(6, 3, 11)->isLegal);
        $this->assertSame(9, $this->evaluate(6, 3, 7)->nextLegalQuantity);
        $this->assertSame(12, $this->evaluate(6, 3, 10)->nextLegalQuantity);
    }

    public function test_moq_one_with_increment_three(): void
    {
        $this->assertTrue($this->evaluate(1, 3, 1)->isLegal);
        $this->assertFalse($this->evaluate(1, 3, 2)->isLegal);
        $this->assertTrue($this->evaluate(1, 3, 4)->isLegal);
        $this->assertTrue($this->evaluate(1, 3, 7)->isLegal);
        $this->assertTrue($this->evaluate(1, 3, 10)->isLegal);
        $this->assertSame(4, $this->evaluate(1, 3, 2)->nextLegalQuantity);
    }

    public function test_increment_without_moq_treats_increment_as_effective_minimum(): void
    {
        $evaluation = $this->evaluate(null, 3, 1);

        $this->assertFalse($evaluation->isLegal);
        $this->assertSame(3, $evaluation->minimumQuantity);
        $this->assertSame(3, $evaluation->increment);
        $this->assertTrue($this->evaluate(null, 3, 3)->isLegal);
        $this->assertFalse($this->evaluate(null, 3, 4)->isLegal);
        $this->assertTrue($this->evaluate(null, 3, 6)->isLegal);
    }

    public function test_zero_or_negative_stored_values_are_ignored_as_unrestricted(): void
    {
        $this->assertTrue($this->evaluate(0, 0, 1)->isLegal);
        $this->assertTrue($this->evaluate(-4, -2, 2)->isLegal);
        $this->assertFalse($this->evaluate(0, 3, 1)->isLegal);
        $this->assertTrue($this->evaluate(0, 3, 3)->isLegal);
        $this->assertTrue($this->evaluate(6, 0, 7)->isLegal);
        $this->assertFalse($this->evaluate(6, 0, 5)->isLegal);
        $this->assertTrue($this->evaluate(null, 0, 1)->isLegal);
        $this->assertFalse($this->evaluate(-1, 3, 1)->isLegal);
        $this->assertTrue($this->evaluate(-1, 3, 3)->isLegal);
    }

    public function test_does_not_round_illegal_quantities(): void
    {
        $evaluation = $this->evaluate(6, 3, 10);

        $this->assertFalse($evaluation->isLegal);
        $this->assertSame(10, $evaluation->eligibleQuantity);
        $this->assertSame(12, $evaluation->nextLegalQuantity);
    }

    private function evaluate(?int $minimum, ?int $increment, int $qty): PurchaseQuantityEvaluation
    {
        $product = new Product;
        $product->id = '019fd0f5-c8d8-718e-b46d-180309aeb88c';
        $product->minimum_order_quantity = $minimum;
        $product->order_increment = $increment;

        return PurchaseQuantityRule::forProduct($product)->evaluate($qty);
    }
}
