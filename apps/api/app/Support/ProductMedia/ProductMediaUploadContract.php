<?php

namespace App\Support\ProductMedia;

/**
 * Canonical catalog product/variant media upload contract.
 *
 * Live admin UI uses catalog media endpoints governed by these limits.
 * Legacy POST /products/{id}/images remains at 2 MB and is not part of this contract.
 */
final class ProductMediaUploadContract
{
    public const MAX_KILOBYTES = 10240;

    public const MAX_WIDTH = 5000;

    public const MAX_HEIGHT = 5000;

    public const MIMES = 'jpg,jpeg,png,webp';

    /**
     * @return list<string>
     */
    public static function imageFileRules(string ...$prefixRules): array
    {
        return [
            ...$prefixRules,
            'file',
            'image',
            'mimes:'.self::MIMES,
            'max:'.self::MAX_KILOBYTES,
            'dimensions:max_width='.self::MAX_WIDTH.',max_height='.self::MAX_HEIGHT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fileMessages(string $attribute = 'file'): array
    {
        return [
            "{$attribute}.image" => 'The file must be a valid image (JPG, PNG, or WebP). HEIC/HEIF is not supported — export or save as JPG.',
            "{$attribute}.mimes" => 'Unsupported image format. Use JPG, PNG, or WebP. HEIC/HEIF is not supported — export or save as JPG.',
            "{$attribute}.max" => 'Image exceeds the 10 MB upload limit.',
            "{$attribute}.dimensions" => 'Image dimensions must be at most 5000×5000 pixels.',
            "{$attribute}.uploaded" => 'The image failed to upload. It may exceed the server upload size limit (10 MB). Try a smaller JPG, PNG, or WebP.',
            "{$attribute}.required" => 'An image file is required.',
            "{$attribute}.required_without" => 'Provide an image file or a public image URL.',
        ];
    }
}
