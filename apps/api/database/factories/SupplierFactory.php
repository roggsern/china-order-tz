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

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('07########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Tanzania',
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
