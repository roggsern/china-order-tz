<?php

namespace App\Support\ProductMedia;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Opt-in upload diagnostics for product/variant media.
 * Enable with PRODUCT_MEDIA_UPLOAD_DIAGNOSTICS=true — never log contents or paths.
 */
final class ProductMediaUploadDiagnostics
{
    public static function enabled(): bool
    {
        return (bool) config('product_media.upload_diagnostics', false);
    }

    public static function logIfEnabled(?UploadedFile $file): void
    {
        if (! self::enabled() || $file === null) {
            return;
        }

        Log::info('product_media_upload_diagnostics', [
            'original_name' => $file->getClientOriginalName(),
            'client_mime' => $file->getClientMimeType(),
            'sniffed_mime' => $file->getMimeType(),
            'guessed_extension' => $file->guessExtension(),
            'size' => $file->getSize(),
            'upload_error' => $file->getError(),
            'is_valid' => $file->isValid(),
        ]);
    }
}
