<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'icon' => null,
            'image' => null,
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
