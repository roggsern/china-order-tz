<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductMediaType;
use App\Models\Admin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class AdminProductAttributeOptionMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_applies_one_image_to_all_variants_sharing_color_option(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        [$product, $blueOption, $blueSmall, $blueMedium, $redSmall] = $this->seedColorSizeMatrix();

        $response = $this->postJson(
            '/api/v1/admin/products/'.$product->id.'/media/apply-to-attribute-option',
            [
                'catalog_attribute_option_id' => $blueOption->id,
                'url' => '/storage/blue.jpg',
                'alt_text' => 'Blue',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.option_value', 'Blue')
            ->assertJsonPath('data.matched_variant_count', 2)
            ->assertJsonPath('data.applied_count', 2)
            ->assertJsonPath('data.skipped_count', 0)
            ->assertJsonPath('data.url', '/storage/blue.jpg');

        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'product_variant_id' => $blueSmall->id,
            'url' => '/storage/blue.jpg',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'product_variant_id' => $blueMedium->id,
            'url' => '/storage/blue.jpg',
            'is_primary' => true,
        ]);
        $this->assertDatabaseMissing('product_media', [
            'product_id' => $product->id,
            'product_variant_id' => $redSmall->id,
        ]);

        // Single upload path reused — two catalog rows, same URL, no product-level row.
        $this->assertSame(2, ProductMedia::query()->where('product_id', $product->id)->count());
        $this->assertSame(0, $product->media()->count());
    }

    public function test_file_upload_applies_shared_storage_url_to_matching_variants(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        [$product, $blueOption, $blueSmall, $blueMedium] = $this->seedColorSizeMatrix();

        $response = $this->post(
            '/api/v1/admin/products/'.$product->id.'/media/apply-to-attribute-option',
            [
                'catalog_attribute_option_id' => $blueOption->id,
                'file' => MinimalTestImage::jpeg('blue-bulk.jpg'),
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.applied_count', 2);

        $urls = ProductMedia::query()
            ->whereIn('product_variant_id', [$blueSmall->id, $blueMedium->id])
            ->pluck('url')
            ->unique()
            ->values();

        $this->assertCount(1, $urls);
        $this->assertStringContainsString('/storage/', (string) $urls->first());
        $this->assertSame(0, \App\Models\ProductImage::query()->count());
    }

    public function test_skips_variants_that_already_have_images(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        [$product, $blueOption, $blueSmall, $blueMedium] = $this->seedColorSizeMatrix();

        ProductMedia::factory()->forVariant($blueMedium)->create([
            'product_id' => $product->id,
            'url' => '/storage/custom-blue-medium.jpg',
            'is_primary' => true,
        ]);

        $this->postJson(
            '/api/v1/admin/products/'.$product->id.'/media/apply-to-attribute-option',
            [
                'catalog_attribute_option_id' => $blueOption->id,
                'url' => '/storage/blue.jpg',
            ],
        )->assertCreated()
            ->assertJsonPath('data.matched_variant_count', 2)
            ->assertJsonPath('data.applied_count', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped_variant_ids.0', $blueMedium->id);

        $this->assertDatabaseHas('product_media', [
            'product_variant_id' => $blueSmall->id,
            'url' => '/storage/blue.jpg',
        ]);
        $this->assertDatabaseHas('product_media', [
            'product_variant_id' => $blueMedium->id,
            'url' => '/storage/custom-blue-medium.jpg',
        ]);
        $this->assertSame(
            1,
            ProductMedia::query()->where('product_variant_id', $blueMedium->id)->count(),
        );
    }

    public function test_rejects_when_no_variants_match_option(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $option = CatalogAttributeOption::factory()->create([
            'value' => 'Orphan',
        ]);

        $this->postJson(
            '/api/v1/admin/products/'.$product->id.'/media/apply-to-attribute-option',
            [
                'catalog_attribute_option_id' => $option->id,
                'url' => '/storage/orphan.jpg',
            ],
        )->assertStatus(422)
            ->assertJsonValidationErrors(['catalog_attribute_option_id']);
    }

    public function test_requires_catalog_update_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::CATALOG_VIEW])->create(),
        );

        $product = Product::factory()->create();
        $option = CatalogAttributeOption::factory()->create();

        $this->postJson(
            '/api/v1/admin/products/'.$product->id.'/media/apply-to-attribute-option',
            [
                'catalog_attribute_option_id' => $option->id,
                'url' => '/storage/denied.jpg',
            ],
        )->assertForbidden();
    }

    public function test_per_variant_override_still_works_after_bulk_apply(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        [$product, $blueOption, $blueSmall] = $this->seedColorSizeMatrix();

        $this->postJson(
            '/api/v1/admin/products/'.$product->id.'/media/apply-to-attribute-option',
            [
                'catalog_attribute_option_id' => $blueOption->id,
                'url' => '/storage/blue.jpg',
            ],
        )->assertCreated();

        $override = $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'url' => '/storage/blue-small-override.jpg',
            'product_variant_id' => $blueSmall->id,
            'is_primary' => true,
        ]);

        $override->assertCreated()
            ->assertJsonPath('data.product_variant_id', $blueSmall->id)
            ->assertJsonPath('data.url', '/storage/blue-small-override.jpg');

        $this->assertSame(
            2,
            ProductMedia::query()
                ->where('product_variant_id', $blueSmall->id)
                ->where('type', ProductMediaType::Image)
                ->count(),
        );
    }

    /**
     * @return array{0: Product, 1: CatalogAttributeOption, 2: ProductVariant, 3: ProductVariant, 4: ProductVariant}
     */
    private function seedColorSizeMatrix(): array
    {
        $product = Product::factory()->create();

        $color = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'color',
        ]);
        $size = CatalogAttribute::factory()->create([
            'name' => 'Size',
            'slug' => 'size',
        ]);

        $blue = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Blue',
            'slug' => 'blue',
        ]);
        $red = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Red',
            'slug' => 'red',
        ]);
        $small = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $size->id,
            'value' => 'S',
            'slug' => 's',
        ]);
        $medium = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $size->id,
            'value' => 'M',
            'slug' => 'm',
        ]);

        $blueSmall = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Blue / S',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $blueMedium = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Blue / M',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $redSmall = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red / S',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->attachOptions($blueSmall, $color->id, $blue->id, $size->id, $small->id);
        $this->attachOptions($blueMedium, $color->id, $blue->id, $size->id, $medium->id);
        $this->attachOptions($redSmall, $color->id, $red->id, $size->id, $small->id);

        return [$product, $blue, $blueSmall, $blueMedium, $redSmall];
    }

    private function attachOptions(
        ProductVariant $variant,
        string $colorAttributeId,
        string $colorOptionId,
        string $sizeAttributeId,
        string $sizeOptionId,
    ): void {
        ProductVariantAttributeValue::factory()->create([
            'product_variant_id' => $variant->id,
            'catalog_attribute_id' => $colorAttributeId,
            'option_id' => $colorOptionId,
            'value_text' => null,
        ]);
        ProductVariantAttributeValue::factory()->create([
            'product_variant_id' => $variant->id,
            'catalog_attribute_id' => $sizeAttributeId,
            'option_id' => $sizeOptionId,
            'value_text' => null,
        ]);
    }
}
