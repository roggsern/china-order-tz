<?php

namespace Database\Factories;

use App\Enums\CMS\CmsHeroContentAlignment;
use App\Enums\CMS\CmsHeroTextTheme;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsHeroSlide>
 */
class CmsHeroSlideFactory extends Factory
{
    protected $model = CmsHeroSlide::class;

    public function definition(): array
    {
        return [
            'cms_homepage_section_id' => CmsHomepageSection::factory()->state([
                'section_type' => CmsHomepageSectionType::Hero,
            ]),
            'name' => fake()->words(3, true),
            'headline' => fake()->sentence(4),
            'subheadline' => fake()->optional()->sentence(6),
            'eyebrow_text' => fake()->optional()->words(2, true),
            'description' => fake()->optional()->paragraph(),
            'desktop_media_id' => null,
            'mobile_media_id' => null,
            'content_alignment' => CmsHeroContentAlignment::Center,
            'text_theme' => CmsHeroTextTheme::Light,
            'primary_cta_label' => null,
            'primary_cta_type' => null,
            'primary_cta_value' => null,
            'secondary_cta_label' => null,
            'secondary_cta_type' => null,
            'secondary_cta_value' => null,
            'position' => 0,
            'status' => CmsStatus::Draft,
            'is_visible' => true,
            'starts_at' => null,
            'ends_at' => null,
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => CmsStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => CmsStatus::Archived]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }
}
