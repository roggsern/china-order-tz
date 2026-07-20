<?php

namespace Tests\Feature\Catalog;

use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_is_repeatable_and_builds_hierarchy(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $expectedRoots = count(CatalogBible::categories());
        $expectedChildren = collect(CatalogBible::categories())
            ->sum(fn (array $root) => count($root['children'] ?? []));

        $this->assertDatabaseCount('categories', $expectedRoots + $expectedChildren);

        $this->getJson('/api/v1/categories?origin=china')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount($expectedRoots, 'data')
            ->assertJsonPath('data.0.slug', 'mens-fashion')
            ->assertJsonPath('data.0.children.0.slug', 'mens-fashion-shirts')
            ->assertJsonPath('data.2.slug', 'electronics')
            ->assertJsonCount(3, 'data.2.children');
    }

    public function test_show_category_by_slug_returns_active_hierarchy(): void
    {
        $this->seed(CategorySeeder::class);

        $this->getJson('/api/v1/categories/electronics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'electronics')
            ->assertJsonPath('data.name', 'Electronics')
            ->assertJsonCount(3, 'data.children')
            ->assertJsonPath('data.children.0.slug', 'electronics-phones');

        $this->getJson('/api/v1/categories/does-not-exist')
            ->assertNotFound();
    }

    public function test_inactive_categories_are_excluded_from_public_endpoints(): void
    {
        $this->seed(CategorySeeder::class);

        $beauty = \App\Models\Category::query()->where('slug', 'beauty')->firstOrFail();
        $beauty->update(['is_active' => false]);

        $this->getJson('/api/v1/categories?origin=china')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'beauty']);

        $this->getJson('/api/v1/categories/beauty')
            ->assertNotFound();
    }

    public function test_brand_seeder_seeds_shared_catalog_brands(): void
    {
        $this->seed(BrandSeeder::class);

        $this->assertCount(23, CatalogBible::brands());
        $this->assertDatabaseCount('brands', 23);
        $this->assertDatabaseHas('brands', ['slug' => 'apple', 'is_featured' => true]);
        $this->assertDatabaseHas('brands', ['slug' => 'jbl']);
        $this->assertDatabaseHas('brands', ['slug' => 'nike']);
        $this->assertDatabaseHas('brands', ['slug' => 'levis']);
        $this->assertDatabaseHas('brands', ['slug' => 'hm']);

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(23, 'data');
    }
}
