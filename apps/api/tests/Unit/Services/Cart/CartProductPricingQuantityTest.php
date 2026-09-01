<?php

namespace Tests\Unit\Services\Cart;

use App\Models\CartItem;
use App\Services\Cart\CartProductPricingQuantity;
use Tests\TestCase;

class CartProductPricingQuantityTest extends TestCase
{
    public function test_map_sums_same_product_across_variants_and_isolates_other_products(): void
    {
        $productA = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $productB = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $items = collect([
            new CartItem(['product_id' => $productA, 'quantity' => 6]),
            new CartItem(['product_id' => $productA, 'quantity' => 4]),
            new CartItem(['product_id' => $productB, 'quantity' => 10]),
        ]);

        $map = CartProductPricingQuantity::mapByProductId($items);

        $this->assertSame(10, $map[$productA]);
        $this->assertSame(10, $map[$productB]);
        $this->assertSame(10, CartProductPricingQuantity::forProduct($items, $productA));
        $this->assertSame(10, CartProductPricingQuantity::forProduct($items, $productB));
        $this->assertSame(0, CartProductPricingQuantity::forProduct($items, 'cccccccc-cccc-cccc-cccc-cccccccccccc'));
    }
}
