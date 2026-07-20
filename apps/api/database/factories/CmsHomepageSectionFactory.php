<?php

namespace Database\Factories;

use App\Enums\CMS\CmsHomepageSectionType;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsHomepageSection>
 */
class CmsHomepageSectionFactory extends Factory
{
    protected $model = CmsHomepageSection::class;

    public function definition(): array
    {
        return [
            'cms_homepage_layout_id' => CmsHomepageLayout::factory(),
            'section_type' => CmsHomepageSectionType::Hero,
            'title' => fake()->optional()->sentence(3),
            'subtitle' => fake()->optional()->sentence(6),
            'position' => 0,
            'is_visible' => true,
            'configuration' => [],
            'created_by' => null,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }

    public function ofType(CmsHomepageSectionType $type): static
    {
        return $this->state(fn () => ['section_type' => $type]);
    }
}
