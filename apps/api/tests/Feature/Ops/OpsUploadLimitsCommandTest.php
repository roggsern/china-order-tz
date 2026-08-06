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
    }

    public function test_ops_upload_limits_text_mode_mentions_contract(): void
    {
        Artisan::call('ops:upload-limits');
        $output = Artisan::output();

        $this->assertStringContainsString('contract_max_kb: 10240', $output);
        $this->assertStringContainsString('legacy_images_max_kb: 2048', $output);
    }
}
