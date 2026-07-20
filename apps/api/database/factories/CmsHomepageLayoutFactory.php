<?php

namespace Database\Factories;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsHomepageLayout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CmsHomepageLayout>
 */
class CmsHomepageLayoutFactory extends Factory
{
    protected $model = CmsHomepageLayout::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'commerce_context' => CmsCommerceContext::Global,
            'status' => CmsStatus::Draft,
            'is_default' => false,
            'default_slot' => null,
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => CmsStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => CmsStatus::Archived,
            'is_default' => false,
            'default_slot' => null,
        ]);
    }

    public function defaultFor(CmsCommerceContext $context): static
    {
        return $this->state(fn () => [
            'commerce_context' => $context,
            'status' => CmsStatus::Active,
            'is_default' => true,
            'default_slot' => $context->value,
        ]);
    }

    public function forContext(CmsCommerceContext $context): static
    {
        return $this->state(fn () => ['commerce_context' => $context]);
    }
}
