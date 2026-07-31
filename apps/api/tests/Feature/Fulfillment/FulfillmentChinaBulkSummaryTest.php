<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\ChinaWorkflowRecord;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentChinaBulkSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_fulfillment_list_exposes_china_bulk_summary_for_china_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $fulfillment = $this->createChinaFulfillmentWithWorkflow([
            'stage' => ChinaWorkflowStage::QcPending,
            'qc_status' => ChinaQcStatus::Pending,
            'metadata' => [
                'purchase_order_ids' => ['po-test-1'],
            ],
        ]);

        $response = $this->getJson('/api/v1/admin/fulfillments?strategy=china');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $row = collect($response->json('data'))
            ->firstWhere('id', $fulfillment->id);

        $this->assertNotNull($row);
        $this->assertSame('china', $row['strategy']);
        $this->assertSame('qc_pending', $row['china']['stage']);
        $this->assertSame('pending', $row['china']['qc_status']);
        $this->assertFalse($row['china']['export_ready']);
        $this->assertTrue($row['china']['has_supplier_purchase']);
        $this->assertSame('established', $row['china']['supplier_purchase_state']);
        $this->assertArrayNotHasKey('export_checklist', $row['china']);
    }

    public function test_fulfillment_list_omits_china_summary_for_local_orders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'buy_from_tz']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::SelfPickup,
        ]);

        $fulfillment = app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product']))
            ->fresh(['warehouseJob']);

        $response = $this->getJson('/api/v1/admin/fulfillments?strategy=local');

        $response->assertOk();

        $row = collect($response->json('data'))
            ->firstWhere('id', $fulfillment->id);

        $this->assertNotNull($row);
        $this->assertSame('local', $row['strategy']);
        $this->assertArrayNotHasKey('china', $row);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createChinaFulfillmentWithWorkflow(array $attributes = []): \App\Models\Fulfillment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'fulfillment_source' => 'imported_from_china',
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
        ]);

        $fulfillment = app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product']));

        $record = ChinaWorkflowRecord::query()->where('order_id', $order->id)->first();
        if ($record === null) {
            ChinaWorkflowRecord::query()->create(array_merge([
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'stage' => ChinaWorkflowStage::AwaitingProcurement,
                'qc_status' => ChinaQcStatus::Pending,
                'metadata' => [],
            ], $attributes));
        } else {
            $record->forceFill(array_merge([
                'fulfillment_id' => $fulfillment->id,
            ], $attributes))->save();
        }

        return $fulfillment->fresh(['chinaWorkflowRecord', 'warehouseJob', 'shipment']);
    }
}
