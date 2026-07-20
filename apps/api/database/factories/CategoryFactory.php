<?php

namespace Database\Factories;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'department_id' => null,
            'parent_id' => null,
            'origin' => CatalogOrigin::China,
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'image' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
        ]);
    }

    public function child(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'department_id' => $parent->department_id,
            'origin' => $parent->origin ?? CatalogOrigin::China,
        ]);
    }

    public function china(): static
    {
        return $this->state(fn () => ['origin' => CatalogOrigin::China]);
    }

    public function tz(): static
    {
        return $this->state(fn () => ['origin' => CatalogOrigin::Tz]);
    }
}
