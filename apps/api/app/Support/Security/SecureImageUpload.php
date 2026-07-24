<?php

namespace App\Support\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class SecureImageUpload
{
    public static function storePublic(UploadedFile $file, string $directory): string
    {
        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: $file->extension()
            ?: 'jpg',
        );
        $filename = Str::uuid()->toString().'.'.$extension;

        return $file->storeAs(trim($directory, '/'), $filename, 'public');
    }
}
