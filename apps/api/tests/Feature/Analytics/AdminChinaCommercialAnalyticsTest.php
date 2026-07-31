<?php

namespace Tests\Feature\Analytics;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\OrderCostSnapshot;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProfitRecord;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\CommerceChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminChinaCommercialAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CommerceChannelSeeder::class);
    }

    private function chinaProfitFixture(): array
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Supplier A']);
        $category = Category::factory()->create(['name' => 'Phones']);
        $chinaChannel = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(50000);
        $product->update([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'commerce_channel_id' => $chinaChannel->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 100000,
            'commerce_channel_id' => $chinaChannel->id,
            'commerce_channel_snapshot' => [
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => CommerceChannelCode::ChinaImport->label(),
            ],
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'line_total' => 100000,
            'total_price' => 100000,
            'unit_price' => 50000,
        ]);

        OrderCostSnapshot::query()->create([
            'order_item_id' => $item->id,
            'supplier_cost' => 60000,
            'shipping_cost' => 10000,
            'other_cost' => 5000,
            'total_cost' => 75000,
            'currency' => 'TZS',
            'exchange_rate' => 1,
            'created_at' => now(),
        ]);

        ProfitRecord::query()->create([
            'order_id' => $order->id,
            'revenue' => '100000.00',
            'total_cost' => '75000.00',
            'gross_profit' => '25000.00',
            'margin_percentage' => '25.0000',
            'currency' => 'TZS',
            'calculated_at' => now(),
        ]);

        return compact('order', 'supplier', 'category', 'product');
    }

    public function test_overview_uses_cost_snapshots_for_china_import(): void
    {
        $this->chinaProfitFixture();

        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ANALYTICS_VIEW])->create());

        $this->getJson('/api/v1/admin/analytics/china/overview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_sales_generated', '100000.00')
            ->assertJsonPath('data.total_landed_cost', '75000.00')
            ->assertJsonPath('data.total_import_value', '60000.00')
            ->assertJsonPath('data.gross_profit', '25000.00')
            ->assertJsonPath('data.units_sold', 2);
    }

    public function test_landed_cost_breakdown_and_supplier_aggregation(): void
    {
        $this->chinaProfitFixture();

        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ANALYTICS_VIEW])->create());

        $this->getJson('/api/v1/admin/analytics/china/landed-cost')
            ->assertOk()
            ->assertJsonPath('data.components.supplier_cost', '60000.00')
            ->assertJsonPath('data.components.china_logistics_and_freight', '10000.00')
            ->assertJsonPath('data.components.other_import_costs', '5000.00');

        $this->getJson('/api/v1/admin/analytics/china/suppliers')
            ->assertOk()
            ->assertJsonPath('data.ranking.0.supplier_name', 'Supplier A')
            ->assertJsonPath('data.ranking.0.margin_percentage', '25.0000');
    }

    public function test_category_aggregation(): void
    {
        $this->chinaProfitFixture();

        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ANALYTICS_VIEW])->create());

        $this->getJson('/api/v1/admin/analytics/china/categories')
            ->assertOk()
            ->assertJsonPath('data.categories.0.category_name', 'Phones')
            ->assertJsonPath('data.categories.0.imported_units', 2);
    }

    public function test_permission_denied_without_analytics_view(): void
    {
        $this->chinaProfitFixture();

        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/analytics/china/overview')->assertForbidden();
    }
}
