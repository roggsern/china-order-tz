<?php

namespace Database\Seeders;

use App\Enums\ShippingMethod;
use App\Models\Product;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

/**
 * Seeds product_shipping_options from China products / legacy flat columns.
 */
class ProductShippingOptionSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $engine = app(ProductShippingOptionEngine::class);

        Product::query()
            ->where(function ($q) {
                $q->whereNotNull('air_shipping_price')
                    ->orWhereNotNull('sea_shipping_price')
                    ->orWhere('fulfillment_source', 'imported_from_china');
            })
            ->orderBy('created_at')
            ->each(function (Product $product) use ($engine, $faker): void {
                if ($product->shippingOptions()->exists()) {
                    return;
                }

                if ($product->air_shipping_price !== null || $product->sea_shipping_price !== null) {
                    $engine->backfillFromLegacy($product);

                    return;
                }

                // China product without legacy prices — realistic demo options.
                $engine->syncForProduct($product, [
                    [
                        'transport_mode' => ShippingMethod::Air->value,
                        'price' => $faker->randomFloat(2, 5000, 25000),
                        'currency' => 'TZS',
                        'is_available' => true,
                        'notes' => 'Manual air freight rate',
                        'sort_order' => 0,
                    ],
                    [
                        'transport_mode' => ShippingMethod::Sea->value,
                        'price' => $faker->randomFloat(2, 2000, 12000),
                        'currency' => 'TZS',
                        'is_available' => true,
                        'notes' => 'Manual sea freight rate',
                        'sort_order' => 1,
                    ],
                ]);
            });
    }
}
