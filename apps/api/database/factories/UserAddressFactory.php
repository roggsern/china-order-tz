<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', 'Other']),
            'recipient_name' => fake()->name(),
            'phone' => '+2557'.fake()->numerify('########'),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->citySuffix(),
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'postal_code' => fake()->optional()->numerify('#####'),
            'country' => 'Tanzania',
            'is_shipping' => true,
            'is_billing' => false,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
