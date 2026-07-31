<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBrandSearchProductivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_search_endpoint_filters_by_name(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        Brand::factory()->create(['name' => 'Zara', 'is_active' => true]);
        Brand::factory()->create(['name' => 'Zara Kids', 'is_active' => true]);
        Brand::factory()->create(['name' => 'Nokia', 'is_active' => true]);

        $this->getJson('/api/v1/admin/brands?search=Zara&per_page=20')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Zara'])
            ->assertJsonFragment(['name' => 'Zara Kids'])
            ->assertJsonMissing(['name' => 'Nokia']);
    }

    public function test_category_filter_prioritizes_linked_brands_with_all_fallback(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $category = Category::factory()->create(['name' => "Women's Blouse"]);
        $fashion = Brand::factory()->create(['name' => 'Fashion Co', 'is_active' => true]);
        $phone = Brand::factory()->create(['name' => 'Nokia', 'is_active' => true]);

        $this->putJson('/api/v1/admin/brands/'.$fashion->id.'/categories', [
            'category_ids' => [$category->id],
        ])->assertOk();

        $filtered = $this->getJson('/api/v1/admin/brands?category_id='.$category->id.'&per_page=50')
            ->assertOk();

        $names = collect($filtered->json('data'))->pluck('name')->all();
        $this->assertContains('Fashion Co', $names);
        $this->assertNotContains('Nokia', $names);

        $all = $this->getJson('/api/v1/admin/brands?category_id='.$category->id.'&all=1&per_page=50')
            ->assertOk();

        $allNames = collect($all->json('data'))->pluck('name')->all();
        $this->assertContains('Fashion Co', $allNames);
        $this->assertContains('Nokia', $allNames);
        $this->assertTrue($phone->is($phone));
    }

    public function test_brand_creation_requires_catalog_create_permission(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->postJson('/api/v1/admin/brands', [
            'name' => 'Unauthorized Brand',
            'is_active' => true,
        ])->assertForbidden();

        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        $this->postJson('/api/v1/admin/brands', [
            'name' => 'Authorized Brand',
            'description' => 'Created inline',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Authorized Brand')
            ->assertJsonPath('data.is_active', true);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/brands?search=Authorized')->assertUnauthorized();
    }
}
