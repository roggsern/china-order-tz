<?php

namespace Database\Factories;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationVisibility;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsNavigationItem>
 */
class CmsNavigationItemFactory extends Factory
{
    protected $model = CmsNavigationItem::class;

    public function definition(): array
    {
        return [
            'navigation_shell_id' => CmsNavigationShell::factory(),
            'parent_id' => null,
            'title' => fake()->words(2, true),
            'icon' => null,
            'position' => 0,
            'visibility' => CmsNavigationVisibility::Public,
            'item_type' => CmsNavigationItemType::Group,
            'target_type' => null,
            'target_value' => null,
            'is_enabled' => true,
        ];
    }

    public function link(string $url = 'https://example.com'): static
    {
        return $this->state(fn () => [
            'item_type' => CmsNavigationItemType::Link,
            'target_type' => CmsCtaTargetType::Url,
            'target_value' => $url,
        ]);
    }

    public function journey(string $value = 'CHINA_IMPORT'): static
    {
        return $this->state(fn () => [
            'item_type' => CmsNavigationItemType::Journey,
            'target_type' => null,
            'target_value' => $value,
        ]);
    }

    public function megaMenu(string $value = 'CHINA_IMPORT'): static
    {
        return $this->state(fn () => [
            'item_type' => CmsNavigationItemType::MegaMenu,
            'target_type' => null,
            'target_value' => $value,
        ]);
    }
}
