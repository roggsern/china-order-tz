<?php

namespace Database\Factories;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsNavigationShell;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CmsNavigationShell>
 */
class CmsNavigationShellFactory extends Factory
{
    protected $model = CmsNavigationShell::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'commerce_context' => CmsCommerceContext::Global,
            'navigation_type' => CmsNavigationType::Primary,
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

    public function forContext(CmsCommerceContext $context): static
    {
        return $this->state(fn () => ['commerce_context' => $context]);
    }

    public function ofType(CmsNavigationType $type): static
    {
        return $this->state(fn () => ['navigation_type' => $type]);
    }

    public function defaultFor(CmsCommerceContext $context, CmsNavigationType $type): static
    {
        return $this->state(fn () => [
            'commerce_context' => $context,
            'navigation_type' => $type,
            'status' => CmsStatus::Active,
            'is_default' => true,
            'default_slot' => CmsNavigationShell::defaultSlotKey($context, $type),
        ]);
    }
}
