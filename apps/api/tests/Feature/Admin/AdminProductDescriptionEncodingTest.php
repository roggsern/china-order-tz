<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Enums\CatalogOrigin;
use Database\Seeders\ProductTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductDescriptionEncodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ProductTypeSeeder::class);
    }

    public function test_create_and_update_store_unicode_description_fields(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $type = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $department = Department::factory()->create();
        $root = Category::factory()->forDepartment($department)->create([
            'origin' => CatalogOrigin::China,
            'product_type_id' => $type->id,
        ]);
        $leaf = Category::factory()->forDepartment($department)->child($root)->create([
            'origin' => CatalogOrigin::China,
        ]);
        $cpt = CatalogProductType::factory()->create([
            'subcategory_id' => $leaf->id,
            'is_active' => true,
        ]);

        $description = "Features • Waterproof – “IP67” … size 10° ✅";
        $short = "Short • line – “ok”";

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Unicode Description Product',
            'category_id' => $leaf->id,
            'catalog_product_type_id' => $cpt->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => Supplier::factory()->create(['is_active' => true, 'country' => 'CN'])->id,
            'price' => 10000,
            'lifecycle_status' => 'draft',
            'description' => $description,
            'short_description' => $short,
        ])->assertCreated();

        $productId = $create->json('data.id');
        $product = Product::query()->findOrFail($productId);

        $this->assertSame($description, $product->description);
        $this->assertSame($short, $product->short_description);
        $this->assertSame($description, $create->json('data.description'));
        $this->assertSame($short, $create->json('data.short_description'));

        $updatedDescription = "Updated • list – “quotes” ’ ™";
        $updatedShort = "Updated short •";

        $this->putJson("/api/v1/admin/products/{$productId}", [
            'description' => $updatedDescription,
            'short_description' => $updatedShort,
        ])->assertOk()
            ->assertJsonPath('data.description', $updatedDescription)
            ->assertJsonPath('data.short_description', $updatedShort);

        $product->refresh();
        $this->assertSame($updatedDescription, $product->description);
        $this->assertSame($updatedShort, $product->short_description);
    }
}
