<?php

namespace Tests\Support;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;

final class MinimalTestImage
{
    /**
     * Tiny valid JPEG. Optional $kilobytes sets the size reported to Laravel's max:* rule
     * (avoids needing GD for large fake images).
     */
    public static function jpeg(string $filename = 'test.jpg', ?int $kilobytes = null): UploadedFile
    {
        /** @var File $file */
        $file = UploadedFile::fake()->createWithContent(
            $filename,
            base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP/bAEMAAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
                true,
            ) ?: '',
        );

        return $kilobytes === null ? $file : $file->size($kilobytes);
    }
}
