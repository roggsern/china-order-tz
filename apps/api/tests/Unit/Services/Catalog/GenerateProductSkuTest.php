<?php

namespace Tests\Unit\Services\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Services\Catalog\GenerateProductSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateProductSkuTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_unique_sku_with_origin_prefix(): void
    {
        $category = Category::factory()->create([
            'origin' => CatalogOrigin::China,
            'slug' => 'electronics',
        ]);

        $sku = app(GenerateProductSku::class)->handle($category);

        $this->assertNotEmpty($sku);
        $this->assertStringStartsWith('COT-CN-', $sku);
    }
}
