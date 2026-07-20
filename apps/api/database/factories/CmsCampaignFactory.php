<?php

namespace Database\Factories;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CmsCampaign>
 */
class CmsCampaignFactory extends Factory
{
    protected $model = CmsCampaign::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->optional()->sentence(),
            'commerce_context' => CmsCommerceContext::Global,
            'status' => CmsStatus::Draft,
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 0,
            'is_default' => false,
            'default_slot' => null,
            'cms_homepage_layout_id' => null,
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

    public function scheduled(?\DateTimeInterface $starts = null, ?\DateTimeInterface $ends = null): static
    {
        return $this->state(fn () => [
            'starts_at' => $starts ?? now()->subHour(),
            'ends_at' => $ends ?? now()->addDay(),
        ]);
    }
}
