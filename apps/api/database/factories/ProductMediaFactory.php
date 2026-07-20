<?php

namespace Database\Factories;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    protected $model = ProductMedia::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => ProductMediaType::Image,
            'url' => '/storage/demo-products/phone.jpg',
            'thumbnail_url' => null,
            'alt_text' => fake()->sentence(3),
            'title' => fake()->words(3, true),
            'sort_order' => 0,
            'is_primary' => false,
            'is_active' => true,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true, 'sort_order' => 0]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'type' => ProductMediaType::Video,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'is_primary' => false,
        ]);
    }
}
