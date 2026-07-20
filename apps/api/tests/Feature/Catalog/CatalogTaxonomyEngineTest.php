<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTaxonomyEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_categories_return_database_tree_including_empty_branches(): void
    {
        $root = Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'origin' => CatalogOrigin::China,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $child = Category::factory()->child($root)->create([
            'name' => 'Phones',
            'slug' => 'electronics-phones',
            'origin' => CatalogOrigin::China,
            'is_active' => true,
        ]);

        Category::factory()->create([
            'name' => 'TZ Fashion',
            'slug' => 'tz-fashion',
            'origin' => CatalogOrigin::Tz,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/categories?origin=china')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.origin', 'china')
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_brands_remain_independent_until_pivot_links_exist(): void
    {
        $category = Category::factory()->create(['origin' => CatalogOrigin::China]);
        $linked = Brand::factory()->create(['name' => 'Linked Brand', 'slug' => 'linked-brand']);
        $unlinked = Brand::factory()->create(['name' => 'Free Brand', 'slug' => 'free-brand']);

        // No pivot yet — category filter does not hide independent brands.
        $this->getJson('/api/v1/brands?category_id='.$category->id.'&with_products=0')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $linked->categories()->attach($category->id);

        $this->getJson('/api/v1/brands?category_id='.$category->id.'&with_products=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->id)
            ->assertJsonMissing(['id' => $unlinked->id]);
    }

    public function test_brand_can_belong_to_multiple_categories_via_pivot(): void
    {
        $brand = Brand::factory()->create();
        $a = Category::factory()->create(['origin' => CatalogOrigin::China]);
        $b = Category::factory()->create(['origin' => CatalogOrigin::Tz]);

        $brand->categories()->attach([$a->id, $b->id]);

        $this->assertSame(2, $brand->categories()->count());
        $this->assertTrue($a->brands()->whereKey($brand->id)->exists());
        $this->assertTrue($b->brands()->whereKey($brand->id)->exists());
    }
}
