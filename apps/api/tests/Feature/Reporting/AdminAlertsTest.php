<?php

namespace Tests\Feature\Reporting;

use App\Models\Admin;
use App\Services\Reporting\AdminAlertService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_alerts_endpoint_returns_operational_and_growth_alerts(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $response = $this->getJson('/api/v1/admin/alerts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period' => ['from', 'to'],
                    'generated_at',
                    'counts' => ['operational', 'growth', 'total'],
                    'alerts',
                ],
            ]);
    }

    public function test_alerts_include_required_fields(): void
    {
        $service = app(AdminAlertService::class);
        $payload = $service->alerts();

        $this->assertArrayHasKey('alerts', $payload);
        $this->assertIsArray($payload['alerts']);

        foreach ($payload['alerts'] as $alert) {
            $this->assertArrayHasKey('severity', $alert);
            $this->assertArrayHasKey('title', $alert);
            $this->assertArrayHasKey('message', $alert);
            $this->assertArrayHasKey('source', $alert);
            $this->assertArrayHasKey('created_at', $alert);
            $this->assertContains($alert['source'], ['operational', 'growth']);
        }
    }

    public function test_alerts_require_reports_view_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::ORDERS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/alerts')->assertForbidden();
    }
}
