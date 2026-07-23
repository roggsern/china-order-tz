<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\VariantInventory;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_active_products_from_mysql(): void
    {
        $active = Product::factory()->create(['name' => 'Active Phone']);
        Product::factory()->inactive()->create(['name' => 'Hidden Phone']);
        Product::factory()->demo()->create(['name' => 'Demo Phone', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.name', 'Active Phone');
    }

    public function test_product_list_supports_pagination_and_newest_first(): void
    {
        $older = Product::factory()->create(['created_at' => now()->subDay()]);
        $newer = Product::factory()->create(['created_at' => now()]);

        $this->getJson('/api/v1/products?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', $newer->id);

        $this->getJson('/api/v1/products?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id);
    }

    public function test_product_list_filters_by_featured_category_brand_and_search(): void
    {
        $category = Category::factory()->create(['slug' => 'phones', 'is_active' => true]);
        $brand = Brand::factory()->create(['slug' => 'samsung', 'is_active' => true]);
        $otherBrand = Brand::factory()->create(['slug' => 'apple', 'is_active' => true]);

        $match = Product::factory()->featured()->create([
            'name' => 'Galaxy Ultra Phone',
            'short_description' => 'Premium Android device',
            'description' => 'Flagship smartphone for power users.',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        Product::factory()->create([
            'name' => 'Budget Tablet',
            'category_id' => $category->id,
            'brand_id' => $otherBrand->id,
            'is_featured' => false,
        ]);

        $this->getJson('/api/v1/products?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/v1/products?category=phones')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/products?brand='.$brand->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/v1/products?search=flagship')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_product_card_resource_exposes_only_customer_safe_fields(): void
    {
        $product = Product::factory()->fromChina()->create([
            'name' => 'Catalog Phone',
            'short_description' => 'Compact and reliable',
        ]);

        ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
            'alt_text' => 'Catalog phone image',
        ]);

        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 5,
            'is_approved' => true,
        ]);

        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 1,
            'is_approved' => false,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.primary_image.path', DemoProductImageLibrary::publicPath('phone.jpg'))
            ->assertJsonPath('data.0.primary_image.url', Storage::disk('public')->url(DemoProductImageLibrary::publicPath('phone.jpg')))
            ->assertJsonPath('data.0.category.slug', $product->category->slug)
            ->assertJsonPath('data.0.brand.slug', $product->brand->slug)
            ->assertJsonPath('data.0.average_rating', 5)
            ->assertJsonPath('data.0.review_count', 1)
            ->assertJsonMissingPath('data.0.cost_price')
            ->assertJsonMissingPath('data.0.supplier')
            ->assertJsonMissingPath('data.0.deleted_at')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.updated_at');
    }

    public function test_product_detail_is_available_by_slug(): void
    {
        $product = Product::factory()->fromChina()->create([
            'slug' => 'premium-headphones',
            'description' => 'Detailed product description.',
        ]);

        ProductImage::factory()->primary()->create(['product_id' => $product->id]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'is_active' => true,
            'price' => 50000,
        ]);

        $activeVariant = $product->variants()->where('name', 'Black')->firstOrFail();
        VariantInventory::query()->create([
            'product_variant_id' => $activeVariant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'is_active' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Hidden Variant',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/products/premium-headphones')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'premium-headphones')
            ->assertJsonPath('data.description', 'Detailed product description.')
            ->assertJsonPath('data.shipping_prices.air', $product->air_shipping_price)
            ->assertJsonPath('data.shipping_prices.sea', $product->sea_shipping_price)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonMissingPath('data.cost_price')
            ->assertJsonMissingPath('data.supplier')
            ->assertJsonMissingPath('data.deleted_at');
    }

    public function test_inactive_product_detail_returns_not_found(): void
    {
        $product = Product::factory()->inactive()->create(['slug' => 'hidden-product']);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertNotFound();
    }

    public function test_demo_product_detail_returns_not_found(): void
    {
        $product = Product::factory()->demo()->create([
            'slug' => 'demo-catalog-product',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertNotFound();
    }

    public function test_lists_active_categories_and_brands_with_active_products(): void
    {
        $category = Category::factory()->create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
        $inactiveCategory = Category::factory()->create(['name' => 'Legacy', 'slug' => 'legacy', 'is_active' => false]);
        $brand = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung', 'is_active' => true]);
        $inactiveBrand = Brand::factory()->create(['name' => 'Old Brand', 'slug' => 'old-brand', 'is_active' => false]);

        Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        Product::factory()->inactive()->create([
            'category_id' => $inactiveCategory->id,
            'brand_id' => $inactiveBrand->id,
        ]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $category->id)
            ->assertJsonPath('data.0.name', 'Phones')
            ->assertJsonPath('data.0.slug', 'phones');

        $this->getJson('/api/v1/categories?with_products=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $brand->id)
            ->assertJsonPath('data.0.name', 'Samsung')
            ->assertJsonPath('data.0.slug', 'samsung');
    }

    public function test_catalog_routes_are_public_without_authentication(): void
    {
        $product = Product::factory()->create(['slug' => 'public-product', 'price' => 15000]);
        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 3, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/products/public-product')->assertOk();
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/brands')->assertOk();
    }
}
