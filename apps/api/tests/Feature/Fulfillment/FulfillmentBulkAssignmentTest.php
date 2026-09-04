<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ActivityEventType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\FulfillmentStatusHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class FulfillmentBulkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fulfillAdmin(): Admin
    {
        return Admin::factory()->withPermissions([
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::ORDERS_FULFILL,
        ])->create();
    }

    private function viewOnlyAdmin(): Admin
    {
        return Admin::factory()->withPermissions([
            AdminPermissions::ORDERS_VIEW,
        ])->create();
    }

    private function makeFulfillment(): Fulfillment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'fulfillment_source' => 'buy_from_tz',
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 25000,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 25000,
            'total_price' => 25000,
            'line_total' => 25000,
        ]);

        return app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product.supplier']));
    }

    /**
     * @return array<string, mixed>
     */
    private function lifecycleSnapshot(Fulfillment $fulfillment): array
    {
        $fresh = $fulfillment->fresh(['order', 'warehouseJob', 'shipment', 'purchaseOrders']);

        return [
            'status' => $fresh?->status,
            'started_at' => $fresh?->started_at?->toJSON(),
            'completed_at' => $fresh?->completed_at?->toJSON(),
            'order_status' => $fresh?->order?->status,
            'warehouse_job_id' => $fresh?->warehouseJob?->id,
            'warehouse_status' => $fresh?->warehouseJob?->status,
            'picker_id' => $fresh?->warehouseJob?->picker_id,
            'packer_id' => $fresh?->warehouseJob?->packer_id,
            'shipment_id' => $fresh?->shipment?->id,
            'purchase_order_ids' => $fresh?->purchaseOrders?->pluck('id')->sort()->values()->all() ?? [],
            'history_count' => FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count(),
        ];
    }

    public function test_authorized_actor_can_bulk_assign_reassign_and_unassign(): void
    {
        $actor = $this->fulfillAdmin();
        $first = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create([
            'name' => 'Amina Owner',
        ]);
        $second = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create([
            'name' => 'Jackson Owner',
        ]);
        $one = $this->makeFulfillment();
        $two = $this->makeFulfillment();
        $beforeOne = $this->lifecycleSnapshot($one);
        $beforeTwo = $this->lifecycleSnapshot($two);

        Sanctum::actingAs($actor);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$one->id, $two->id],
            'assigned_to' => $first->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requested', 2)
            ->assertJsonPath('data.changed', 2)
            ->assertJsonPath('data.unchanged', 0)
            ->assertJsonPath('data.assigned_to', $first->id)
            ->assertJsonPath('data.assignee.id', $first->id)
            ->assertJsonPath('data.assignee.name', 'Amina Owner')
            ->assertJsonMissingPath('data.data');

        $this->assertSame($first->id, $one->fresh()?->assigned_to);
        $this->assertSame($first->id, $two->fresh()?->assigned_to);
        $this->assertSame(2, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->whereIn('subject_id', [$one->id, $two->id])
            ->count());
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentBulkActionCompleted->value)
            ->count());

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$one->id, $two->id],
            'assigned_to' => $second->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.changed', 2)
            ->assertJsonPath('data.assigned_to', $second->id);

        $this->assertSame($second->id, $one->fresh()?->assigned_to);
        $this->assertSame($second->id, $two->fresh()?->assigned_to);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$one->id, $two->id],
            'assigned_to' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.changed', 2)
            ->assertJsonPath('data.assigned_to', null)
            ->assertJsonPath('data.assignee', null);

        $this->assertNull($one->fresh()?->assigned_to);
        $this->assertNull($two->fresh()?->assigned_to);
        $this->assertSame(6, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->whereIn('subject_id', [$one->id, $two->id])
            ->count());
        $this->assertSame($beforeOne, $this->lifecycleSnapshot($one));
        $this->assertSame($beforeTwo, $this->lifecycleSnapshot($two));
        $this->assertSame(FulfillmentStatus::Pending, $one->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $one->fresh('order')?->order?->status);
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentBulkActionCompleted->value)
            ->count());
    }

    public function test_super_admin_can_be_bulk_assigned(): void
    {
        $actor = $this->fulfillAdmin();
        $super = Admin::factory()->create([
            'name' => 'Super Owner',
            'is_super_admin' => true,
        ]);
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => $super->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $super->id)
            ->assertJsonPath('data.changed', 1);

        $this->assertSame($super->id, $fulfillment->fresh()?->assigned_to);
    }

    public function test_unauthorized_actor_cannot_bulk_assign(): void
    {
        $target = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($this->viewOnlyAdmin());

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => $target->id,
        ])->assertForbidden();

        $this->assertNull($fulfillment->fresh()?->assigned_to);
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_invalid_and_ineligible_assignees_are_rejected(): void
    {
        $actor = $this->fulfillAdmin();
        $fulfillment = $this->makeFulfillment();
        $inactive = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->inactive()->create();
        $deleted = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $deleted->delete();
        $ineligible = $this->viewOnlyAdmin();

        Sanctum::actingAs($actor);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => $inactive->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => $deleted->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => $ineligible->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => '00000000-0000-4000-8000-000000000099',
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => 'not-a-uuid',
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->assertNull($fulfillment->fresh()?->assigned_to);
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_unknown_soft_deleted_and_mixed_fulfillment_ids_fail_without_writes(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $valid = $this->makeFulfillment();
        $deleted = $this->makeFulfillment();
        $deleted->delete();

        Sanctum::actingAs($actor);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => ['00000000-0000-4000-8000-000000000088'],
            'assigned_to' => $owner->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['fulfillment_ids']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$deleted->id],
            'assigned_to' => $owner->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['fulfillment_ids']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$valid->id, '00000000-0000-4000-8000-000000000077'],
            'assigned_to' => $owner->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['fulfillment_ids']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$valid->id, $deleted->id],
            'assigned_to' => $owner->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['fulfillment_ids']);

        $this->assertNull($valid->fresh()?->assigned_to);
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_duplicate_empty_missing_and_oversized_payloads_are_rejected(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $fulfillment = $this->makeFulfillment();
        $ids = array_fill(0, 51, $fulfillment->id);
        $ids[0] = $fulfillment->id;
        for ($index = 1; $index < 51; $index++) {
            $ids[$index] = sprintf('00000000-0000-4000-8000-%012d', $index);
        }

        Sanctum::actingAs($actor);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id, $fulfillment->id],
            'assigned_to' => $owner->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['fulfillment_ids.1']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [],
            'assigned_to' => $owner->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['fulfillment_ids']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => $ids,
            'assigned_to' => $owner->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['fulfillment_ids']);

        $this->assertNull($fulfillment->fresh()?->assigned_to);
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_same_assignee_rows_are_noop_and_mixed_batch_audits_only_changes(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $already = $this->makeFulfillment();
        $needsChange = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$already->id}/assignment", [
            'assigned_to' => $owner->id,
        ])->assertOk();

        ActivityLog::query()->delete();

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$already->id, $needsChange->id],
            'assigned_to' => $owner->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.requested', 2)
            ->assertJsonPath('data.changed', 1)
            ->assertJsonPath('data.unchanged', 1);

        $this->assertSame($owner->id, $already->fresh()?->assigned_to);
        $this->assertSame($owner->id, $needsChange->fresh()?->assigned_to);
        $this->assertSame(1, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
        $this->assertSame(1, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $needsChange->id)
            ->count());
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $already->id)
            ->count());
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentBulkActionCompleted->value)
            ->count());
    }

    public function test_bulk_assignment_route_is_not_captured_as_fulfillment_id(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$fulfillment->id],
            'assigned_to' => $owner->id,
        ])->assertOk()->assertJsonPath('data.requested', 1);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment/assignment', [
            'assigned_to' => $owner->id,
        ])->assertNotFound();
    }

    public function test_mid_batch_failure_rolls_back_all_writes(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $first = $this->makeFulfillment();
        $second = $this->makeFulfillment();
        $saves = 0;

        Fulfillment::saving(function (Fulfillment $fulfillment) use (&$saves): void {
            if (! $fulfillment->isDirty('assigned_to')) {
                return;
            }

            $saves++;
            if ($saves >= 2) {
                throw new RuntimeException('forced bulk assignment failure');
            }
        });

        Sanctum::actingAs($actor);

        $this->patchJson('/api/v1/admin/fulfillments/bulk-assignment', [
            'fulfillment_ids' => [$first->id, $second->id],
            'assigned_to' => $owner->id,
        ])->assertStatus(500);

        $this->assertNull($first->fresh()?->assigned_to);
        $this->assertNull($second->fresh()?->assigned_to);
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }
}
