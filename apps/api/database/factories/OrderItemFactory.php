<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $item): void {
            $unit = (string) ($item->unit_price ?? '0.00');
            $qty = (string) ($item->quantity ?? 1);
            $line = bcmul($unit, $qty, 2);

            $item->unit_price_snapshot = $unit;
            $item->line_total = $line;
            $item->total_price = $line;
            $item->currency_snapshot = $item->currency_snapshot ?: ($item->currency ?: 'TZS');
            $item->currency = $item->currency ?: 'TZS';
            $item->product_name_snapshot = $item->product_name_snapshot ?: $item->product_name;
            $item->sku_snapshot = $item->sku_snapshot ?: $item->sku;
            $item->variant_name_snapshot = $item->variant_name_snapshot ?: $item->variant_name;
            $item->shipping_mode_snapshot = $item->shipping_mode_snapshot ?: $item->shipping_method;
            $item->shipping_price_snapshot = $item->shipping_price_snapshot ?: $item->shipping_price;
        });
    }

    public function definition(): array
    {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'air_shipping_price' => fake()->randomFloat(2, 3000, 12000),
            'sea_shipping_price' => fake()->randomFloat(2, 1500, 6000),
        ]);
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = (string) $product->price;
        $lineTotal = bcmul($unitPrice, (string) $quantity, 2);
        $shippingMode = fake()->randomElement(['air', 'sea']);
        $shippingPrice = $shippingMode === 'air'
            ? (string) $product->air_shipping_price
            : (string) $product->sea_shipping_price;
        $shippingSubtotal = bcmul($shippingPrice, (string) $quantity, 2);

        return [
            'order_id' => Order::factory(),
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name_snapshot' => $product->name,
            'product_slug_snapshot' => $product->slug,
            'sku_snapshot' => $product->sku,
            'brand_name_snapshot' => $brand->name,
            'variant_name_snapshot' => null,
            'variant_sku_snapshot' => null,
            'currency_snapshot' => 'TZS',
            'unit_price_snapshot' => $unitPrice,
            'shipping_mode_snapshot' => $shippingMode,
            'shipping_price_snapshot' => $shippingPrice,
            'shipping_notes_snapshot' => 'Manual '.$shippingMode.' freight',
            'attributes_snapshot' => null,
            'product_image_snapshot' => null,
            'image_snapshot' => null,
            'product_name' => $product->name,
            'variant_name' => null,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'total_price' => $lineTotal,
            'currency' => 'TZS',
            'shipping_method' => $shippingMode,
            'shipping_price' => $shippingPrice,
            'shipping_subtotal' => $shippingSubtotal,
        ];
    }

    public function withVariant(): static
    {
        return $this->state(function (array $attributes) {
            $variant = ProductVariant::factory()->create();
            $quantity = $attributes['quantity'] ?? fake()->numberBetween(1, 3);
            $unitPrice = (string) ($variant->price ?? $variant->product->price);
            $lineTotal = bcmul($unitPrice, (string) $quantity, 2);

            return [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name_snapshot' => $variant->product->name,
                'product_slug_snapshot' => $variant->product->slug,
                'sku_snapshot' => $variant->sku,
                'brand_name_snapshot' => $variant->product->brand?->name,
                'variant_name_snapshot' => $variant->name,
                'variant_sku_snapshot' => $variant->sku,
                'unit_price_snapshot' => $unitPrice,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'total_price' => $lineTotal,
            ];
        });
    }
}
