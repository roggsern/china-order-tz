<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Department;
use App\Services\Catalog\HomeCareCatalogBibleRootService;
use Database\Seeders\CategorySeeder;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeCareCatalogBibleRootTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_care_root_resolves_through_catalog_bible_and_seeder(): void
    {
        $this->assertContains(
            'home-care',
            collect(CatalogBible::categories())->pluck('slug')->all(),
        );

        $this->seed(CategorySeeder::class);

        $root = Category::query()->where('slug', 'home-care')->first();

        $this->assertNotNull($root);
        $this->assertSame('Home Care', $root->name);
        $this->assertSame(CatalogOrigin::China, $root->origin);
        $this->assertNull($root->department_id);
        $this->assertNull($root->store_id);
        $this->assertNull($root->parent_id);
        $this->assertTrue($root->is_active);
    }

    public function test_ensure_creates_bible_root_and_reparents_existing_children(): void
    {
        $department = Department::factory()->create([
            'name' => 'Home Care',
            'slug' => 'home-care',
            'is_active' => true,
        ]);

        $childSlugs = HomeCareCatalogBibleRootService::CHILD_SLUGS;
        foreach ($childSlugs as $index => $slug) {
            Category::factory()->create([
                'department_id' => $department->id,
                'name' => $slug,
                'slug' => $slug,
                'parent_id' => null,
                'origin' => CatalogOrigin::China,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        $departmentUpdatedAt = $department->fresh()->updated_at?->toJSON();

        $result = app(HomeCareCatalogBibleRootService::class)->ensure(dryRun: false);

        $this->assertFalse($result['dry_run']);
        $this->assertTrue($result['root_created']);
        $this->assertEqualsCanonicalizing($childSlugs, $result['reparented_slugs']);
        $this->assertSame([], $result['missing_child_slugs']);

        $root = Category::query()->findOrFail($result['root_id']);
        $this->assertSame('home-care', $root->slug);
        $this->assertNull($root->department_id);
        $this->assertNull($root->store_id);
        $this->assertNull($root->parent_id);

        $children = Category::query()
            ->where('parent_id', $root->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertSame($childSlugs, $children->pluck('slug')->all());
        $this->assertTrue($children->every(
            fn (Category $category) => $category->department_id === $department->id
                && $category->store_id === null
                && $category->origin === CatalogOrigin::China
                && $category->is_active,
        ));

        $department->refresh();
        $this->assertSame($departmentUpdatedAt, $department->updated_at?->toJSON());
        $this->assertSame(1, Department::query()->where('slug', 'home-care')->count());
    }

    public function test_ensure_is_idempotent_and_keeps_children_attached(): void
    {
        $department = Department::factory()->create([
            'name' => 'Home Care',
            'slug' => 'home-care',
            'is_active' => true,
        ]);

        foreach (HomeCareCatalogBibleRootService::CHILD_SLUGS as $slug) {
            Category::factory()->create([
                'department_id' => $department->id,
                'slug' => $slug,
                'name' => $slug,
                'parent_id' => null,
                'origin' => CatalogOrigin::China,
                'store_id' => null,
                'is_active' => true,
            ]);
        }

        $service = app(HomeCareCatalogBibleRootService::class);
        $first = $service->ensure(dryRun: false);
        $second = $service->ensure(dryRun: false);

        $this->assertFalse($second['root_created']);
        $this->assertSame([], $second['reparented_slugs']);
        $this->assertEqualsCanonicalizing(
            HomeCareCatalogBibleRootService::CHILD_SLUGS,
            $second['already_attached_slugs'],
        );

        $this->assertSame(
            4,
            Category::query()->where('parent_id', $first['root_id'])->count(),
        );
    }

    public function test_ensure_does_not_create_a_department(): void
    {
        $this->assertNull(Department::query()->where('slug', 'home-care')->first());

        app(HomeCareCatalogBibleRootService::class)->ensure(dryRun: false);

        $this->assertNull(Department::query()->where('slug', 'home-care')->first());
        $this->assertNotNull(Category::query()->where('slug', 'home-care')->first());
    }

    public function test_artisan_command_execute_flag(): void
    {
        $department = Department::factory()->create([
            'name' => 'Home Care',
            'slug' => 'home-care',
            'is_active' => true,
        ]);

        Category::factory()->create([
            'department_id' => $department->id,
            'slug' => 'pest-control',
            'name' => 'Pest Control',
            'parent_id' => null,
            'origin' => CatalogOrigin::China,
            'store_id' => null,
            'is_active' => true,
        ]);

        $this->artisan('catalog:ensure-home-care-bible-root')
            ->assertSuccessful();

        $this->assertNull(Category::query()->where('slug', 'home-care')->first());

        $this->artisan('catalog:ensure-home-care-bible-root', ['--execute' => true])
            ->assertSuccessful();

        $root = Category::query()->where('slug', 'home-care')->first();
        $this->assertNotNull($root);
        $this->assertNull($root->department_id);

        $pestControl = Category::query()->where('slug', 'pest-control')->firstOrFail();
        $this->assertSame($root->id, $pestControl->parent_id);
        $this->assertSame($department->id, $pestControl->department_id);
    }
}
