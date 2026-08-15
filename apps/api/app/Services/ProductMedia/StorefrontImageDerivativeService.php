<?php

namespace App\Services\ProductMedia;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates bounded storefront display derivatives with GD.
 *
 * Original uploads under products/{uuid}.ext are never overwritten.
 * Derivatives live at products/storefront/{stem}.webp.
 */
final class StorefrontImageDerivativeService
{
    public const MAX_EDGE_PX = 1600;

    public const WEBP_QUALITY = 82;

    public const DIRECTORY = 'products/storefront';

    public const MAX_INPUT_EDGE_PX = 5000;

    /**
     * @return array{path: string, url: string, width: int, height: int}|null
     */
    public function generateFromPublicPath(string $originalRelativePath): ?array
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            Log::warning('storefront_image_derivative.gd_unavailable', [
                'path' => $originalRelativePath,
            ]);

            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $originalRelativePath), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relative)) {
            return null;
        }

        $absolute = $disk->path($relative);
        if (! is_file($absolute) || ! is_readable($absolute)) {
            return null;
        }

        $info = @getimagesize($absolute);
        if ($info === false || ! isset($info[0], $info[1], $info['mime'])) {
            return null;
        }

        $srcWidth = (int) $info[0];
        $srcHeight = (int) $info[1];
        $mime = (string) $info['mime'];

        if ($srcWidth < 1 || $srcHeight < 1) {
            return null;
        }

        if ($srcWidth > self::MAX_INPUT_EDGE_PX || $srcHeight > self::MAX_INPUT_EDGE_PX) {
            Log::warning('storefront_image_derivative.input_too_large', [
                'path' => $relative,
                'width' => $srcWidth,
                'height' => $srcHeight,
            ]);

            return null;
        }

        $source = $this->createImageResource($absolute, $mime);
        if ($source === false) {
            return null;
        }

        try {
            $source = $this->applyExifOrientation($absolute, $mime, $source);
            $srcWidth = imagesx($source);
            $srcHeight = imagesy($source);

            [$dstWidth, $dstHeight] = $this->boundedDimensions($srcWidth, $srcHeight);
            $preserveAlpha = $this->mimeSupportsAlpha($mime);

            $destination = imagecreatetruecolor($dstWidth, $dstHeight);
            if ($destination === false) {
                return null;
            }

            try {
                if ($preserveAlpha) {
                    imagealphablending($destination, false);
                    imagesavealpha($destination, true);
                    $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
                    if ($transparent !== false) {
                        imagefilledrectangle($destination, 0, 0, $dstWidth, $dstHeight, $transparent);
                    }
                } else {
                    $white = imagecolorallocate($destination, 255, 255, 255);
                    if ($white !== false) {
                        imagefilledrectangle($destination, 0, 0, $dstWidth, $dstHeight, $white);
                    }
                    imagealphablending($destination, true);
                }

                imagecopyresampled(
                    $destination,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $dstWidth,
                    $dstHeight,
                    $srcWidth,
                    $srcHeight,
                );

                $derivativeRelative = $this->derivativeRelativePath($relative);
                $derivativeAbsolute = $disk->path($derivativeRelative);
                $directory = dirname($derivativeAbsolute);
                if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                    return null;
                }

                if (! imagewebp($destination, $derivativeAbsolute, self::WEBP_QUALITY)) {
                    return null;
                }

                @chmod($derivativeAbsolute, 0644);

                return [
                    'path' => $derivativeRelative,
                    'url' => $disk->url($derivativeRelative),
                    'width' => $dstWidth,
                    'height' => $dstHeight,
                ];
            } finally {
                imagedestroy($destination);
            }
        } catch (Throwable $exception) {
            Log::warning('storefront_image_derivative.failed', [
                'path' => $relative,
                'message' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            imagedestroy($source);
        }
    }

    public function derivativeRelativePath(string $originalRelativePath): string
    {
        $relative = ltrim(str_replace('\\', '/', $originalRelativePath), '/');
        $stem = pathinfo($relative, PATHINFO_FILENAME);
        $stem = Str::of($stem)->replaceMatches('/[^A-Za-z0-9_-]/', '')->toString();
        if ($stem === '') {
            $stem = Str::uuid()->toString();
        }

        return self::DIRECTORY.'/'.$stem.'.webp';
    }

    public function derivativeExistsForOriginal(string $originalRelativePath): bool
    {
        return Storage::disk('public')->exists($this->derivativeRelativePath($originalRelativePath));
    }

    /**
     * Resolve a public-disk relative path from a product media URL.
     */
    public function resolvePublicRelativePathFromUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $value = (string) $url;
        $publicBase = rtrim(Storage::disk('public')->url(''), '/');

        if (Str::startsWith($value, $publicBase.'/')) {
            return ltrim(Str::after($value, $publicBase.'/'), '/');
        }

        if (Str::startsWith($value, '/storage/')) {
            return ltrim(Str::after($value, '/storage/'), '/');
        }

        if (Str::startsWith($value, 'storage/')) {
            return ltrim(Str::after($value, 'storage/'), '/');
        }

        if (Str::startsWith($value, 'products/')) {
            return $value;
        }

        return null;
    }

    /**
     * @return \GdImage|false
     */
    private function createImageResource(string $absolutePath, string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($absolutePath)
                : false,
            default => false,
        };
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function applyExifOrientation(string $absolutePath, string $mime, mixed $source): mixed
    {
        if ($mime !== 'image/jpeg' && $mime !== 'image/jpg') {
            return $source;
        }

        if (! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($absolutePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation <= 1) {
            return $source;
        }

        $rotated = match ($orientation) {
            2 => $this->flipImage($source, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($source, 180, 0),
            4 => $this->flipImage($source, IMG_FLIP_VERTICAL),
            5 => $this->rotateThenFlip($source, 270, IMG_FLIP_HORIZONTAL),
            6 => imagerotate($source, -90, 0),
            7 => $this->rotateThenFlip($source, 90, IMG_FLIP_HORIZONTAL),
            8 => imagerotate($source, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $source;
        }

        if ($rotated !== $source) {
            imagedestroy($source);
        }

        return $rotated;
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage|false
     */
    private function flipImage(mixed $source, int $mode): mixed
    {
        if (! imageflip($source, $mode)) {
            return false;
        }

        return $source;
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage|false
     */
    private function rotateThenFlip(mixed $source, int $angle, int $flipMode): mixed
    {
        $rotated = imagerotate($source, $angle, 0);
        if ($rotated === false) {
            return false;
        }

        if ($rotated !== $source) {
            imagedestroy($source);
        }

        if (! imageflip($rotated, $flipMode)) {
            return $rotated;
        }

        return $rotated;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function boundedDimensions(int $width, int $height): array
    {
        $maxEdge = max($width, $height);
        if ($maxEdge <= self::MAX_EDGE_PX) {
            return [$width, $height];
        }

        $scale = self::MAX_EDGE_PX / $maxEdge;

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    private function mimeSupportsAlpha(string $mime): bool
    {
        return in_array($mime, ['image/png', 'image/webp'], true);
    }
}
