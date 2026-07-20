<?php

namespace Tests\Feature\Warehouse;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Warehouse\WarehouseEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Order, 1: Fulfillment, 2: WarehouseJob}
     */
    private function makePaidFulfillmentWithJob(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'imported_from_china']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'total_price' => 20000,
            'line_total' => 20000,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product.supplier']));
        $job = $fulfillment->fresh('warehouseJob')->warehouseJob;

        $this->assertNotNull($job);

        return [$order->fresh(), $fulfillment->fresh(), $job->fresh()];
    }

    public function test_warehouse_job_created_with_fulfillment(): void
    {
        [, $fulfillment, $job] = $this->makePaidFulfillmentWithJob();

        $this->assertSame(WarehouseJobStatus::Pending, $job->status);
        $this->assertSame($fulfillment->id, $job->fulfillment_id);
        $this->assertMatchesRegularExpression('/^COTZ-WH-\d{8}-\d{6}$/', $job->job_number);
        $this->assertSame(1, WarehouseJob::query()->where('order_id', $fulfillment->order_id)->count());
    }

    public function test_forward_status_transitions(): void
    {
        [, , $job] = $this->makePaidFulfillmentWithJob();
        $engine = app(WarehouseEngine::class);

        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
            WarehouseJobStatus::ReadyToShip,
        ] as $status) {
            $job = $engine->updateStatus($job, ['status' => $status->value]);
            $this->assertSame($status, $job->status);
        }

        $this->assertNotNull($job->picked_at);
        $this->assertNotNull($job->packed_at);
        $this->assertNotNull($job->ready_at);
        $this->assertSame(
            FulfillmentStatus::ReadyForShipping,
            $job->fulfillment->fresh()->status,
        );
    }

    public function test_invalid_backward_transitions_rejected(): void
    {
        [, , $job] = $this->makePaidFulfillmentWithJob();
        $engine = app(WarehouseEngine::class);

        $job = $engine->updateStatus($job, ['status' => WarehouseJobStatus::Picking->value]);
        $job = $engine->updateStatus($job, ['status' => WarehouseJobStatus::Picked->value]);
        $job = $engine->updateStatus($job, ['status' => WarehouseJobStatus::Packing->value]);
        $job = $engine->updateStatus($job, ['status' => WarehouseJobStatus::Packed->value]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $engine->updateStatus($job, ['status' => WarehouseJobStatus::Picked->value]);
    }

    public function test_ready_to_ship_cannot_go_back_to_packing(): void
    {
        [, , $job] = $this->makePaidFulfillmentWithJob();
        $engine = app(WarehouseEngine::class);

        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
            WarehouseJobStatus::ReadyToShip,
        ] as $status) {
            $job = $engine->updateStatus($job, ['status' => $status->value]);
        }

        Sanctum::actingAs(Admin::factory()->create());
        $this->patchJson("/api/v1/admin/warehouse/{$job->id}/status", [
            'status' => 'packing',
        ])->assertStatus(422);
    }

    public function test_assign_picker_and_packer(): void
    {
        [, , $job] = $this->makePaidFulfillmentWithJob();
        $picker = Admin::factory()->create(['name' => 'Picker One']);
        $packer = Admin::factory()->create(['name' => 'Packer One']);

        Sanctum::actingAs($picker);

        $this->patchJson("/api/v1/admin/warehouse/{$job->id}/assign-picker")
            ->assertOk()
            ->assertJsonPath('data.picker_id', $picker->id);

        Sanctum::actingAs($packer);
        $this->patchJson("/api/v1/admin/warehouse/{$job->id}/assign-packer")
            ->assertOk()
            ->assertJsonPath('data.packer_id', $packer->id);
    }

    public function test_relationships(): void
    {
        [$order, $fulfillment, $job] = $this->makePaidFulfillmentWithJob();

        $this->assertTrue($order->warehouseJob->is($job));
        $this->assertTrue($fulfillment->warehouseJob->is($job));
        $this->assertTrue($job->order->is($order));
        $this->assertTrue($job->fulfillment->is($fulfillment));
    }

    public function test_admin_warehouse_index_and_show(): void
    {
        [, , $job] = $this->makePaidFulfillmentWithJob();
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/admin/warehouse')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/v1/admin/warehouse/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.job_number', $job->job_number)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_guest_and_customer_cannot_access_warehouse(): void
    {
        [, , $job] = $this->makePaidFulfillmentWithJob();

        $this->getJson('/api/v1/admin/warehouse')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/v1/admin/warehouse/{$job->id}/status", [
            'status' => 'picking',
        ])->assertUnauthorized();
    }

    public function test_cancel_allowed_before_ready_to_ship(): void
    {
        [, , $job] = $this->makePaidFulfillmentWithJob();
        $engine = app(WarehouseEngine::class);

        $job = $engine->updateStatus($job, ['status' => WarehouseJobStatus::Picking->value]);
        $job = $engine->updateStatus($job, ['status' => WarehouseJobStatus::Cancelled->value]);

        $this->assertSame(WarehouseJobStatus::Cancelled, $job->status);
    }
}
