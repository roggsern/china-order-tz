<?php

namespace Tests\Feature\Shipping;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Shipping\ShippingDurationResolver;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminShippingRateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingMethodSeeder::class);
    }

    public function test_guest_and_customer_cannot_access_shipping_rates(): void
    {
        $this->getJson('/api/v1/admin/shipping/rates')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/shipping/rates')->assertUnauthorized();
        $this->putJson('/api/v1/admin/shipping/rates/air_freight', [
            'price' => 30000,
        ])->assertUnauthorized();
    }

    public function test_permission_denied_without_shipping_view_or_manage(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/shipping/rates')->assertForbidden();
        $this->putJson('/api/v1/admin/shipping/rates/air_freight', [
            'price' => 30000,
            'estimated_min_days' => 7,
            'estimated_max_days' => 12,
            'estimated_delivery_days' => 10,
        ])->assertForbidden();
    }

    public function test_view_permission_can_list_but_not_update(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::SHIPPING_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/shipping/rates')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    [
                        'method',
                        'price',
                        'estimated_min_days',
                        'estimated_max_days',
                        'estimated_delivery_days',
                        'active',
                    ],
                ],
            ]);

        $this->putJson('/api/v1/admin/shipping/rates/air_freight', [
            'price' => 30000,
            'estimated_min_days' => 7,
            'estimated_max_days' => 12,
            'estimated_delivery_days' => 10,
        ])->assertForbidden();
    }

    public function test_manage_permission_updates_rate_and_writes_audit(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::SHIPPING_VIEW,
            AdminPermissions::SHIPPING_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/shipping/rates/air_freight', [
            'price' => 27500.5,
            'estimated_min_days' => 8,
            'estimated_max_days' => 14,
            'estimated_delivery_days' => 11,
            'active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.method', 'air_freight')
            ->assertJsonPath('data.price', 27500.5)
            ->assertJsonPath('data.estimated_min_days', 8)
            ->assertJsonPath('data.estimated_max_days', 14)
            ->assertJsonPath('data.estimated_delivery_days', 11)
            ->assertJsonPath('data.active', true);

        $method = ShippingMethod::query()->where('code', 'air_freight')->firstOrFail();
        $rate = ShippingRate::query()
            ->where('shipping_method_id', $method->id)
            ->whereNull('min_weight')
            ->whereNull('max_weight')
            ->firstOrFail();

        $this->assertSame('27500.50', (string) $rate->base_cost);
        $this->assertSame(8, $rate->estimated_min_days);
        $this->assertSame(14, $rate->estimated_max_days);
        $this->assertSame(11, $rate->estimated_delivery_days);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::ShippingRateUpdated->value,
            'actor_id' => $admin->id,
            'subject_id' => $rate->id,
        ]);

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::ShippingRateUpdated->value)
            ->where('subject_id', $rate->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('air_freight', $log->metadata['method'] ?? null);
        $this->assertSame('25000.00', (string) ($log->old_values['price'] ?? ''));
        $this->assertSame('27500.50', (string) ($log->new_values['price'] ?? ''));
        $this->assertSame(8, $log->new_values['estimated_min_days'] ?? null);
        $this->assertSame(11, $log->new_values['estimated_delivery_days'] ?? null);
        $this->assertSame(14, $log->new_values['estimated_max_days'] ?? null);
    }

    public function test_rejects_invalid_duration_window(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::SHIPPING_VIEW,
                AdminPermissions::SHIPPING_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/shipping/rates/sea_freight', [
            'estimated_min_days' => 40,
            'estimated_max_days' => 35,
            'estimated_delivery_days' => 38,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['estimated_delivery_days']);

        $this->putJson('/api/v1/admin/shipping/rates/local_delivery', [
            'estimated_min_days' => 1,
            'estimated_max_days' => 5,
            'estimated_delivery_days' => 6,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['estimated_delivery_days']);

        $method = ShippingMethod::query()->where('code', 'sea_freight')->firstOrFail();
        $rate = ShippingRate::query()
            ->where('shipping_method_id', $method->id)
            ->firstOrFail();

        $this->assertSame(35, $rate->estimated_min_days);
        $this->assertSame(45, $rate->estimated_max_days);
        $this->assertSame(40, $rate->estimated_delivery_days);
    }

    public function test_can_deactivate_rate(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::SHIPPING_VIEW,
                AdminPermissions::SHIPPING_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/shipping/rates/local_delivery', [
            'active' => false,
        ])->assertOk()
            ->assertJsonPath('data.active', false);

        $method = ShippingMethod::query()->where('code', 'local_delivery')->firstOrFail();
        $this->assertFalse(
            (bool) ShippingRate::query()
                ->where('shipping_method_id', $method->id)
                ->value('is_active'),
        );
    }

    public function test_admin_update_flows_through_duration_resolver_without_contract_change(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::SHIPPING_VIEW,
                AdminPermissions::SHIPPING_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/shipping/rates/air_freight', [
            'estimated_min_days' => 9,
            'estimated_max_days' => 15,
            'estimated_delivery_days' => 12,
            'active' => true,
        ])->assertOk();

        $resolved = app(ShippingDurationResolver::class)->resolveAir();

        $this->assertSame(9, $resolved['min_days']);
        $this->assertSame(15, $resolved['max_days']);
        $this->assertSame(12, $resolved['typical_days']);
        $this->assertSame('air_freight', $resolved['method_code']);
        $this->assertSame('shipping_rates', $resolved['source']);

        $this->getJson('/api/v1/shipping/durations')
            ->assertOk()
            ->assertJsonPath('data.air.min_days', 9)
            ->assertJsonPath('data.air.max_days', 15)
            ->assertJsonPath('data.air.typical_days', 12)
            ->assertJsonPath('data.air.source', 'shipping_rates');
    }
}
