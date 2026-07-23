<?php

namespace Tests\Feature\Production;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Closure #6 — production safety gates (no business redesign).
 */
class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_health_endpoint_reports_database_check(): void
    {
        $response = $this->getJson('/api/v1/health')->assertOk();

        $response->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', true)
            ->assertJsonStructure([
                'status',
                'service',
                'checks' => ['database'],
                'timestamp',
            ]);

        if (app()->environment('production')) {
            $response->assertJsonMissingPath('environment')
                ->assertJsonMissingPath('debug');
        } else {
            $response->assertJsonStructure([
                'environment',
                'debug',
                'checks' => ['database', 'queue', 'cache', 'storage', 'scheduler'],
            ]);
        }
    }

    public function test_mock_payment_blocked_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->postJson("/api/v1/admin/payments/{$payment->id}/mock", [
            'result' => 'success',
        ])->assertForbidden();
    }

    public function test_simulate_nmb_callback_blocked_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->postJson("/api/v1/admin/payments/{$payment->id}/simulate-nmb-callback", [
            'result' => 'success',
        ])->assertForbidden();
    }
}
