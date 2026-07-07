<?php

namespace Database\Factories;

use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAddress>
 */
class DeliveryAddressFactory extends Factory
{
    protected $model = DeliveryAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('07########'),
            'country' => 'Tanzania',
            'region' => fake()->randomElement(['Dar es Salaam', 'Arusha', 'Mwanza']),
            'city' => 'Dar es Salaam',
            'district' => fake()->randomElement(['Kinondoni', 'Ilala', 'Temeke']),
            'street' => fake()->streetAddress(),
            'landmark' => fake()->optional()->sentence(3),
            'postal_code' => fake()->optional()->postcode(),
        ];
    }
}
