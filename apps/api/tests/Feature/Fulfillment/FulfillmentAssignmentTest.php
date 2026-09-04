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
use Tests\TestCase;

class FulfillmentAssignmentTest extends TestCase
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

    public function test_orders_fulfill_actor_can_list_eligible_assignees(): void
    {
        $actor = $this->fulfillAdmin();
        $eligible = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create([
            'name' => 'Eligible Operator',
        ]);
        $super = Admin::factory()->create([
            'name' => 'Super Owner',
            'is_super_admin' => true,
        ]);
        $inactive = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->inactive()->create([
            'name' => 'Inactive Operator',
        ]);
        $deleted = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create([
            'name' => 'Deleted Operator',
        ]);
        $deleted->delete();
        $ineligible = $this->viewOnlyAdmin();
        $ineligible->update(['name' => 'View Only']);

        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/admin/fulfillments/assignees')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($eligible->id, $ids);
        $this->assertContains($super->id, $ids);
        $this->assertContains($actor->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
        $this->assertNotContains($ineligible->id, $ids);

        foreach ($response->json('data') as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
        }
    }

    public function test_actor_without_orders_fulfill_cannot_list_assignees(): void
    {
        Sanctum::actingAs($this->viewOnlyAdmin());

        $this->getJson('/api/v1/admin/fulfillments/assignees')->assertForbidden();
    }

    public function test_authorized_actor_can_assign_reassign_and_unassign(): void
    {
        $actor = $this->fulfillAdmin();
        $first = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create([
            'name' => 'First Owner',
        ]);
        $second = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create([
            'name' => 'Second Owner',
        ]);
        $fulfillment = $this->makeFulfillment();
        $orderStatus = $fulfillment->order?->status;
        $startedAt = $fulfillment->started_at;
        $completedAt = $fulfillment->completed_at;
        $status = $fulfillment->status;

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $first->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_to', $first->id)
            ->assertJsonPath('data.assignee.id', $first->id)
            ->assertJsonPath('data.assignee.name', 'First Owner')
            ->assertJsonPath('data.status', FulfillmentStatus::Pending->value);

        $this->assertSame(1, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $fulfillment->id)
            ->count());

        $assignLog = ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $fulfillment->id)
            ->latest('created_at')
            ->first();
        $this->assertNotNull($assignLog);
        $this->assertArrayHasKey('assigned_to', $assignLog->old_values ?? []);
        $this->assertNull($assignLog->old_values['assigned_to']);
        $this->assertSame($first->id, $assignLog->new_values['assigned_to'] ?? null);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $second->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $second->id)
            ->assertJsonPath('data.assignee.id', $second->id);

        $assignmentLogs = ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $fulfillment->id)
            ->get();
        $this->assertTrue($assignmentLogs->contains(
            static fn (ActivityLog $log): bool => ($log->old_values['assigned_to'] ?? null) === $first->id
                && ($log->new_values['assigned_to'] ?? null) === $second->id,
        ));

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', null)
            ->assertJsonPath('data.assignee', null);

        $assignmentLogs = ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $fulfillment->id)
            ->get();
        $this->assertTrue($assignmentLogs->contains(
            static fn (ActivityLog $log): bool => ($log->old_values['assigned_to'] ?? null) === $second->id
                && array_key_exists('assigned_to', $log->new_values ?? [])
                && $log->new_values['assigned_to'] === null,
        ));

        $fresh = $fulfillment->fresh(['order']);
        $this->assertSame($status, $fresh?->status);
        $this->assertEquals($startedAt, $fresh?->started_at);
        $this->assertEquals($completedAt, $fresh?->completed_at);
        $this->assertSame($orderStatus, $fresh?->order?->status);
        $this->assertSame(0, FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count());
        $this->assertSame(3, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $fulfillment->id)
            ->count());
    }

    public function test_same_assignee_request_is_idempotent_without_duplicate_audit(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $owner->id,
        ])->assertOk();

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $owner->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $owner->id);

        $this->assertSame(1, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->where('subject_id', $fulfillment->id)
            ->count());
    }

    public function test_invalid_and_ineligible_targets_are_rejected(): void
    {
        $actor = $this->fulfillAdmin();
        $fulfillment = $this->makeFulfillment();
        $inactive = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->inactive()->create();
        $ineligible = $this->viewOnlyAdmin();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => 'not-a-uuid',
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => '00000000-0000-4000-8000-000000000099',
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $inactive->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $ineligible->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->assertNull($fulfillment->fresh()?->assigned_to);
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_actor_without_orders_fulfill_cannot_assign(): void
    {
        $fulfillment = $this->makeFulfillment();
        $target = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();

        Sanctum::actingAs($this->viewOnlyAdmin());

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $target->id,
        ])->assertForbidden();
    }

    public function test_status_endpoint_uses_the_same_assignee_eligibility_as_assignment(): void
    {
        $actor = $this->fulfillAdmin();
        $eligible = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $super = Admin::factory()->create(['is_super_admin' => true]);
        $inactive = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->inactive()->create();
        $deleted = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $deleted->delete();
        $ineligible = $this->viewOnlyAdmin();
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => $eligible->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $eligible->id)
            ->assertJsonPath('data.status', FulfillmentStatus::Pending->value);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => $super->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $super->id);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => $inactive->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => $deleted->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => $ineligible->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => '00000000-0000-4000-8000-000000000099',
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => 'not-a-uuid',
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

        $this->assertSame($super->id, $fulfillment->fresh()?->assigned_to);
        $this->assertSame(0, FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count());
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_status_only_and_null_assigned_to_compatibility_remain(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'status' => FulfillmentStatus::Processing->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', FulfillmentStatus::Processing->value)
            ->assertJsonPath('data.assigned_to', null);

        $this->assertSame(1, FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count());

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => $owner->id,
        ])->assertOk()->assertJsonPath('data.assigned_to', $owner->id);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'assigned_to' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', null)
            ->assertJsonPath('data.status', FulfillmentStatus::Processing->value);

        $this->assertSame(1, FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count());
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_combined_status_and_assignment_keeps_existing_side_effects(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create();
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/status", [
            'status' => FulfillmentStatus::Processing->value,
            'assigned_to' => $owner->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', FulfillmentStatus::Processing->value)
            ->assertJsonPath('data.assigned_to', $owner->id);

        $this->assertSame(1, FulfillmentStatusHistory::query()->where('fulfillment_id', $fulfillment->id)->count());
        $this->assertSame(0, ActivityLog::query()
            ->where('event_type', ActivityEventType::FulfillmentAssigned->value)
            ->count());
    }

    public function test_detail_response_reflects_assignee(): void
    {
        $actor = $this->fulfillAdmin();
        $owner = Admin::factory()->withPermissions([AdminPermissions::ORDERS_FULFILL])->create([
            'name' => 'Detail Owner',
        ]);
        $fulfillment = $this->makeFulfillment();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/fulfillments/{$fulfillment->id}/assignment", [
            'assigned_to' => $owner->id,
        ])->assertOk();

        $this->getJson("/api/v1/admin/fulfillments/{$fulfillment->id}")
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $owner->id)
            ->assertJsonPath('data.assignee.name', 'Detail Owner');
    }
}
