<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\PaymentMethodDefinition;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPosPaymentMethodAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        PaymentMethodDefinition::query()->create([
            'code' => 'CASH',
            'name' => 'Cash',
            'is_active' => true,
            'sort_order' => 1,
            'config' => ['pos_enabled' => true],
        ]);
    }

    public function test_pos_payment_methods_index_requires_permission(): void
    {
        $this->getJson('/api/v1/admin/pos-payment-methods')->assertUnauthorized();

        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/pos-payment-methods')->assertForbidden();
    }

    public function test_pos_payment_methods_index_allows_view_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::POS_PAYMENT_METHODS_VIEW,
            ])->create(),
        );

        $this->getJson('/api/v1/admin/pos-payment-methods')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CASH');
    }
}
