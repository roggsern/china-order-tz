<?php

namespace Tests\Unit\Support\ProductMedia;

use App\Support\ProductMedia\ProductMediaUploadDiagnostics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class ProductMediaUploadDiagnosticsTest extends TestCase
{
    public function test_diagnostics_are_disabled_by_default(): void
    {
        config(['product_media.upload_diagnostics' => false]);

        Log::spy();

        ProductMediaUploadDiagnostics::logIfEnabled(MinimalTestImage::jpeg('diag.jpg'));

        Log::shouldNotHaveReceived('info');
    }

    public function test_diagnostics_log_safe_metadata_when_enabled(): void
    {
        config(['product_media.upload_diagnostics' => true]);

        Log::spy();

        /** @var UploadedFile $file */
        $file = MinimalTestImage::jpeg('diag-photo.png');
        ProductMediaUploadDiagnostics::logIfEnabled($file);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'product_media_upload_diagnostics'
                    && ($context['original_name'] ?? null) === 'diag-photo.png'
                    && array_key_exists('client_mime', $context)
                    && array_key_exists('sniffed_mime', $context)
                    && array_key_exists('guessed_extension', $context)
                    && array_key_exists('size', $context)
                    && array_key_exists('upload_error', $context)
                    && array_key_exists('is_valid', $context)
                    && ! array_key_exists('path', $context)
                    && ! array_key_exists('contents', $context);
            });
    }
}
