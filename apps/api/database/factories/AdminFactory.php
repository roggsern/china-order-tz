<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    /**
     * The current password being used by the factory.
     * Plain string — Admin model casts password => hashed.
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('07########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'is_super_admin' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(function (array $attributes) {
            // Prefer the seeded administrator role so tests that call RoleSeeder
            // do not also insert a random Role via Role::factory() (slug collisions).
            $administratorId = Role::query()->where('slug', 'administrator')->value('id');

            return array_filter([
                'is_super_admin' => true,
                'role_id' => $administratorId,
            ], static fn ($value) => $value !== null);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
