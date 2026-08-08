<?php

namespace App\Console\Commands;

use App\Support\ProductMedia\ProductMediaUploadContract;
use Illuminate\Console\Command;

class OpsUploadLimitsCommand extends Command
{
    protected $signature = 'ops:upload-limits {--json : Output JSON}';

    protected $description = 'Show effective PHP upload limits vs the catalog product-media contract.';

    public function handle(): int
    {
        $uploadMax = (string) ini_get('upload_max_filesize');
        $postMax = (string) ini_get('post_max_size');
        $memoryLimit = (string) ini_get('memory_limit');
        $gdLoaded = extension_loaded('gd');
        $getImageSizeAvailable = function_exists('getimagesize');

        $expected = [
            'upload_max_filesize' => '10M',
            'post_max_size' => '12M',
            'memory_limit' => '256M',
            'gd' => true,
            'getimagesize' => true,
        ];

        $actual = [
            'upload_max_filesize' => $uploadMax,
            'post_max_size' => $postMax,
            'memory_limit' => $memoryLimit,
            'gd' => $gdLoaded,
            'getimagesize' => $getImageSizeAvailable,
        ];

        $limitsOk = $this->normalizeIniSize($uploadMax) >= $this->normalizeIniSize($expected['upload_max_filesize'])
            && $this->normalizeIniSize($postMax) >= $this->normalizeIniSize($expected['post_max_size'])
            && $this->normalizeIniSize($memoryLimit) >= $this->normalizeIniSize($expected['memory_limit']);
        $gdOk = $gdLoaded && $getImageSizeAvailable;
        $ok = $limitsOk && $gdOk;

        $payload = [
            'ok' => $ok,
            'contract' => [
                'formats' => explode(',', ProductMediaUploadContract::MIMES),
                'max_kilobytes' => ProductMediaUploadContract::MAX_KILOBYTES,
                'max_width' => ProductMediaUploadContract::MAX_WIDTH,
                'max_height' => ProductMediaUploadContract::MAX_HEIGHT,
                'legacy_images_endpoint_max_kilobytes' => 2048,
                'legacy_note' => 'POST /api/v1/admin/products/{id}/images remains at 2 MB and is not the live catalog media contract.',
                'gd_note' => 'Laravel dimensions validation requires the GD extension and getimagesize().',
            ],
            'expected_php' => $expected,
            'actual_php' => $actual,
            'verify_shell' => 'php -m | grep -i gd; php -r "var_export(function_exists(\'getimagesize\'));"',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('ok: '.($ok ? 'yes' : 'no'));
            $this->line('upload_max_filesize: '.$uploadMax.' (expected >= 10M)');
            $this->line('post_max_size: '.$postMax.' (expected >= 12M)');
            $this->line('memory_limit: '.$memoryLimit.' (expected >= 256M)');
            $this->line('gd: '.($gdLoaded ? 'yes' : 'no').' (required for catalog media dimensions)');
            $this->line('getimagesize: '.($getImageSizeAvailable ? 'yes' : 'no'));
            $this->line('contract_max_kb: '.ProductMediaUploadContract::MAX_KILOBYTES);
            $this->line('legacy_images_max_kb: 2048 (not live catalog media UI)');
            $this->line('shell: '.$payload['verify_shell']);

            if (! $limitsOk) {
                $this->warn('PHP upload limits are below the catalog media contract. Rebuild the API image with docker/php/uploads.prod.ini.');
            }
            if (! $gdOk) {
                $this->warn('PHP GD / getimagesize is missing. Rebuild the API image with GD enabled in docker/php/Dockerfile.');
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function normalizeIniSize(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-1') {
            return PHP_INT_MAX;
        }

        if (! preg_match('/^(\d+)([KMG])?$/i', $trimmed, $matches)) {
            return 0;
        }

        $n = (int) $matches[1];
        $unit = strtoupper($matches[2] ?? '');

        return match ($unit) {
            'G' => $n * 1024 * 1024 * 1024,
            'M' => $n * 1024 * 1024,
            'K' => $n * 1024,
            default => $n,
        };
    }
}
