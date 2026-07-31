<?php

namespace Tests\Feature\Reporting;

use App\Services\Reporting\ReportingEngine;
use App\Services\Storefront\StorefrontAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_partial_payload_when_storefront_analytics_fails(): void
    {
        $this->mock(StorefrontAnalyticsService::class, function ($mock): void {
            $mock->shouldReceive('traffic')->andThrow(new \RuntimeException('Analytics unavailable'));
        });

        $dashboard = app(ReportingEngine::class)->dashboard();

        $this->assertNull($dashboard['storefront_traffic']);
        $this->assertArrayHasKey('section_errors', $dashboard);
        $this->assertSame('Unavailable', $dashboard['section_errors']['storefront_traffic']);
        $this->assertIsArray($dashboard['sales']);
        $this->assertArrayHasKey('overview', $dashboard);
    }
}
