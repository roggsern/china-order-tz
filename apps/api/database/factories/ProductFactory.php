<?php

namespace Database\Factories;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 5000, 500000);

        return [
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'supplier_id' => Supplier::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'sku' => strtoupper(Str::random(8)),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->sentence(),
            'price' => $price,
            'compare_at_price' => $price * 1.2,
            'cost_price' => $price * 0.6,
            'weight' => fake()->randomFloat(3, 0.1, 10),
            'dimensions' => fake()->numerify('##x##x## cm'),
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'sort_order' => 0,
            'catalog_product_type_id' => null,
            'meta_title' => ucwords($name),
            'meta_description' => fake()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            if (filled($product->commerce_channel_id)) {
                $channel = CommerceChannel::query()->find($product->commerce_channel_id);
                if ($channel !== null) {
                    $code = CommerceChannelCode::tryFrom($channel->code) ?? CommerceChannelCode::ChinaImport;
                    $product->fulfillment_source = $code->fulfillmentSource();
                }

                return;
            }

            $code = CommerceChannelCode::fromFulfillmentSource($product->fulfillment_source);
            $product->commerce_channel_id = self::resolveChannelId($code);
            $product->fulfillment_source = $code->fulfillmentSource();
        });
    }

    public function chinaImport(): static
    {
        return $this->state(fn () => [
            'commerce_channel_id' => self::resolveChannelId(CommerceChannelCode::ChinaImport),
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
        ]);
    }

    public function tzLocal(): static
    {
        return $this->state(fn () => [
            'commerce_channel_id' => self::resolveChannelId(CommerceChannelCode::TzLocal),
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ]);
    }

    private static function resolveChannelId(CommerceChannelCode $code): string
    {
        $id = CommerceChannel::query()->where('code', $code->value)->value('id');
        if ($id !== null) {
            return (string) $id;
        }

        $channel = match ($code) {
            CommerceChannelCode::TzLocal => CommerceChannel::factory()->tanzania()->create(),
            default => CommerceChannel::factory()->china()->create(),
        };

        return $channel->id;
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);
    }

    public function demo(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_demo' => true,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function fromChina(): static
    {
        return $this->chinaImport()->state(fn (array $attributes) => [
            'supplier_id' => Supplier::factory()->china(),
            'air_shipping_price' => fake()->randomFloat(2, 3000, 15000),
            'sea_shipping_price' => fake()->randomFloat(2, 1500, 8000),
        ])->afterCreating(function (Product $product): void {
            if ($product->shippingOptions()->exists()) {
                return;
            }

            app(\App\Services\ProductShipping\ProductShippingOptionEngine::class)
                ->backfillFromLegacy($product);
        });
    }

    public function fromDar(): static
    {
        return $this->tzLocal()->state(fn (array $attributes) => [
            'supplier_id' => Supplier::factory(),
        ]);
    }
}
