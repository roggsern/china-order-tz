<?php

namespace Tests\Unit\Services\AdminProducts;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\AdminProducts\ProductDeletionLifecycle;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductDeletionLifecycleMediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_db_failure_leaves_physical_files_intact(): void
    {
        Storage::fake('public');

        $product = Product::factory()->tzLocal()->create();
        $path = 'products/db-failure-keeps-file.jpg';
        Storage::disk('public')->put($path, 'keep-me');
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => '/storage/'.$path,
        ]);

        // forceDelete requires onlyTrashed lock — active product causes transaction failure.
        try {
            app(ProductDeletionLifecycle::class)->forceDelete($product);
            $this->fail('Expected forceDelete to fail for non-trashed product.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertNotNull(Product::query()->find($product->id));
        $this->assertNotNull(ProductMedia::query()->where('product_id', $product->id)->first());
    }
}
