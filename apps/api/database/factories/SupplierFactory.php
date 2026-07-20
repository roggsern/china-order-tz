<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '_')).'_'.Str::upper(Str::random(4)),
            'slug' => $slug,
            'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('07########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Tanzania',
            'payment_terms' => fake()->optional()->randomElement(['Net 30', 'Net 15', 'COD']),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function china(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'China',
        ]);
    }
}
