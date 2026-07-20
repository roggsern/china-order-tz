<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = \App\Models\Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        // Always suffix the slug. Job titles like "Manager" otherwise collide with
        // RoleSeeder system roles (manager, support, …) and flake CI with UNIQUE
        // constraint failures when Admin::factory() creates a role after seeding.
        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'description' => fake()->sentence(),
        ];
    }
}
