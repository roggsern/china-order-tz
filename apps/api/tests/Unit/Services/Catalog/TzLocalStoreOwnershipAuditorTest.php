<?php

namespace Tests\Unit\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Product;
use App\Models\Store;
use App\Services\Catalog\TzLocalStoreOwnershipAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TzLocalStoreOwnershipAuditorTest extends TestCase
{
    use RefreshDatabase;

    private TzLocalStoreOwnershipAuditor $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditor = app(TzLocalStoreOwnershipAuditor::class);
    }

    public function test_detects_tz_local_products_without_store_id(): void
    {
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();

        Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => null,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => Store::query()->create([
                'code' => 'TST1',
                'name' => 'Test Store',
                'slug' => 'test-store',
                'is_active' => true,
            ])->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $report = $this->auditor->audit();

        $this->assertSame(1, $report['total_affected']);
        $this->assertSame(1, $report['active']);
        $this->assertSame(0, $report['draft']);
        $this->assertSame(1, $report['requires_manual_assignment']);
    }

    public function test_audit_breaks_down_lifecycle_counts(): void
    {
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $store = Store::query()->create([
            'code' => 'CATS',
            'name' => 'Category Store',
            'slug' => 'category-store',
            'is_active' => true,
        ]);
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create([
            'store_id' => $store->id,
        ]);

        Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => null,
            'category_id' => $category->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);
        Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => null,
            'lifecycle_status' => ProductLifecycleStatus::Archived,
        ]);
        Product::factory()->create([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => null,
            'lifecycle_status' => ProductLifecycleStatus::OutOfStock,
            'is_active' => true,
        ]);

        $report = $this->auditor->audit();

        $this->assertSame(3, $report['total_affected']);
        $this->assertSame(0, $report['active']);
        $this->assertSame(1, $report['out_of_stock']);
        $this->assertSame(1, $report['draft']);
        $this->assertSame(1, $report['archived']);
        $this->assertSame(1, $report['auto_assignable_from_category']);
    }
}
