<?php

namespace Tests\Feature\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Events\Audit\OrderCreated as OrderCreatedAudit;
use App\Events\Audit\ProductUpdated;
use App\Events\Audit\ShippingOptionUpdated;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\ActivityLogFormatter;
use App\Services\Audit\AuditPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class ActivityLogPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_records_activity_from_business_event(): void
    {
        $user = User::factory()->create();
        $order = \App\Models\Order::factory()->create(['user_id' => $user->id]);

        event(OrderCreatedAudit::fromOrder($order, $user));

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::OrderCreated->value,
            'subject_id' => $order->id,
            'actor_id' => $user->id,
            'actor_type' => ActivityActorType::Customer->value,
        ]);
    }

    public function test_activity_creation_via_platform_listener(): void
    {
        $admin = Admin::factory()->create();
        $product = Product::factory()->create(['name' => 'Audit Phone', 'price' => 100000]);

        event(ProductUpdated::fromChanges(
            $product,
            ['price' => '100000'],
            ['price' => '120000'],
            $admin,
        ));

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::ProductUpdated->value,
            'actor_type' => ActivityActorType::Admin->value,
            'actor_id' => $admin->id,
            'subject_id' => $product->id,
        ]);

        $log = ActivityLog::query()->where('subject_id', $product->id)->first();
        $this->assertSame('100000', (string) $log->old_values['price']);
        $this->assertSame('120000', (string) $log->new_values['price']);
    }

    public function test_old_new_values_formatter_changes(): void
    {
        $formatter = app(ActivityLogFormatter::class);
        $changes = $formatter->changes(
            ['price' => '180000', 'currency' => 'TZS'],
            ['price' => '220000', 'currency' => 'TZS'],
        );

        $this->assertCount(1, $changes);
        $this->assertSame('price', $changes[0]['field']);
        $this->assertSame('180000', $changes[0]['old']);
        $this->assertSame('220000', $changes[0]['new']);
    }

    public function test_shipping_option_event_records_price_change(): void
    {
        $admin = Admin::factory()->create();
        $product = Product::factory()->create();
        $option = \App\Models\ProductShippingOption::factory()->create([
            'product_id' => $product->id,
            'price' => 180000,
        ]);

        event(ShippingOptionUpdated::fromOption(
            $option,
            ['price' => '180000'],
            ['price' => '220000'],
            $admin,
            'updated',
        ));

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::ShippingOptionUpdated->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('180000', (string) $log->old_values['price']);
        $this->assertSame('220000', (string) $log->new_values['price']);
    }

    public function test_admin_can_list_and_filter_activity_logs(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        ActivityLog::factory()->create([
            'event_type' => ActivityEventType::OrderCreated,
            'description' => 'Order COTZ-1 was created.',
        ]);
        ActivityLog::factory()->create([
            'event_type' => ActivityEventType::AdminLogin,
            'description' => 'Admin logged in.',
        ]);

        $this->getJson('/api/v1/admin/activity-logs?event_type=order_created')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_type', 'order_created');

        $this->getJson('/api/v1/admin/activity-logs?search=logged%20in')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_view_activity_log_details(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $log = ActivityLog::factory()->create([
            'event_type' => ActivityEventType::ShippingOptionUpdated,
            'old_values' => ['price' => '180000'],
            'new_values' => ['price' => '220000'],
        ]);

        $this->getJson("/api/v1/admin/activity-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.changes.0.field', 'price')
            ->assertJsonPath('data.changes.0.old', '180000')
            ->assertJsonPath('data.changes.0.new', '220000');
    }

    public function test_customer_cannot_access_activity_logs(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/activity-logs')->assertUnauthorized();
    }

    public function test_guest_rejected(): void
    {
        $this->getJson('/api/v1/admin/activity-logs')->assertUnauthorized();
    }

    public function test_append_only_cannot_update_or_delete(): void
    {
        $log = ActivityLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->update(['description' => 'tampered']);
    }

    public function test_append_only_cannot_delete(): void
    {
        $log = ActivityLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_resolve_actor_relationship(): void
    {
        $admin = Admin::factory()->create();
        $log = ActivityLog::factory()->create([
            'actor_type' => ActivityActorType::Admin,
            'actor_id' => $admin->id,
        ]);

        $resolved = $log->resolveActor();
        $this->assertTrue($resolved->is($admin));
    }

    public function test_audit_platform_publish_records_log(): void
    {
        $platform = app(AuditPlatform::class);
        $admin = Admin::factory()->create();

        $platform->publish(ProductUpdated::fromChanges(
            Product::factory()->create(),
            ['name' => 'A'],
            ['name' => 'B'],
            $admin,
        ));

        $this->assertSame(1, ActivityLog::query()->where('event_type', 'product_updated')->count());
    }

    public function test_admin_login_event_is_recorded(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'audit-admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => 'audit-admin@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::AdminLogin->value,
            'actor_id' => $admin->id,
        ]);
    }
}
