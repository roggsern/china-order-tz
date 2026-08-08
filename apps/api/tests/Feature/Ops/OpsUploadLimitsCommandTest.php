<?php

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpsUploadLimitsCommandTest extends TestCase
{
    public function test_ops_upload_limits_reports_catalog_contract_json(): void
    {
        Artisan::call('ops:upload-limits', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('"max_kilobytes": 10240', $output);
        $this->assertStringContainsString('"legacy_images_endpoint_max_kilobytes": 2048', $output);
        $this->assertStringContainsString('upload_max_filesize', $output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ok', $decoded);
        $this->assertSame(10240, $decoded['contract']['max_kilobytes'] ?? null);
        $this->assertArrayHasKey('gd', $decoded['expected_php'] ?? []);
        $this->assertArrayHasKey('gd', $decoded['actual_php'] ?? []);
        $this->assertArrayHasKey('getimagesize', $decoded['actual_php'] ?? []);
        $this->assertTrue($decoded['expected_php']['gd'] ?? false);
        $this->assertTrue($decoded['expected_php']['getimagesize'] ?? false);
        $this->assertIsBool($decoded['actual_php']['gd'] ?? null);
        $this->assertIsBool($decoded['actual_php']['getimagesize'] ?? null);
    }

    public function test_ops_upload_limits_text_mode_mentions_contract(): void
    {
        Artisan::call('ops:upload-limits');
        $output = Artisan::output();

        $this->assertStringContainsString('contract_max_kb: 10240', $output);
        $this->assertStringContainsString('legacy_images_max_kb: 2048', $output);
        $this->assertStringContainsString('gd:', $output);
        $this->assertStringContainsString('getimagesize:', $output);
    }
}
