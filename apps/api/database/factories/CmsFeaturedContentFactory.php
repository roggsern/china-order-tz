<?php

namespace Database\Factories;

use App\Enums\CMS\CmsFeaturedDisplayStyle;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsFeaturedContent>
 */
class CmsFeaturedContentFactory extends Factory
{
    protected $model = CmsFeaturedContent::class;

    public function definition(): array
    {
        return [
            'cms_homepage_section_id' => CmsHomepageSection::factory()->state([
                'section_type' => CmsHomepageSectionType::FeaturedProducts,
            ]),
            'title' => fake()->words(3, true),
            'subtitle' => fake()->optional()->sentence(4),
            'source_type' => CmsFeaturedSourceType::Manual,
            'limit' => 8,
            'sort_order' => 'default',
            'display_style' => CmsFeaturedDisplayStyle::Grid,
            'configuration' => ['item_ids' => [], 'item_type' => 'PRODUCT'],
            'position' => 0,
            'status' => CmsStatus::Draft,
            'is_visible' => true,
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => CmsStatus::Active]);
    }

    public function ofSource(CmsFeaturedSourceType $type): static
    {
        return $this->state(fn () => ['source_type' => $type]);
    }
}
