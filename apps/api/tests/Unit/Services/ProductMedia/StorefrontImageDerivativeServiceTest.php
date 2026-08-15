<?php

namespace Tests\Unit\Services\ProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ProductMedia\StorefrontImageDerivativeBackfillService;
use App\Services\ProductMedia\StorefrontImageDerivativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontImageDerivativeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD + imagewebp required for storefront derivatives.');
        }
    }

    public function test_generates_webp_derivative_for_jpeg_and_respects_max_edge(): void
    {
        Storage::fake('public');

        $upload = UploadedFile::fake()->image('large.jpg', 2000, 1200);
        $path = $upload->storeAs('products', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.jpg', 'public');

        $result = app(StorefrontImageDerivativeService::class)->generateFromPublicPath($path);

        $this->assertNotNull($result);
        $this->assertSame(
            'products/storefront/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.webp',
            $result['path'],
        );
        $this->assertTrue(Storage::disk('public')->exists($result['path']));
        $this->assertTrue(Storage::disk('public')->exists($path));

        $originalInfo = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame(2000, $originalInfo[0]);
        $this->assertSame(1200, $originalInfo[1]);

        $this->assertSame(1600, $result['width']);
        $this->assertSame(960, $result['height']);
        $this->assertLessThanOrEqual(StorefrontImageDerivativeService::MAX_EDGE_PX, max($result['width'], $result['height']));

        $derivativeInfo = getimagesize(Storage::disk('public')->path($result['path']));
        $this->assertSame('image/webp', $derivativeInfo['mime']);
    }

    public function test_generates_derivative_for_png_and_preserves_alpha(): void
    {
        Storage::fake('public');

        $image = imagecreatetruecolor(100, 80);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, 100, 80, $transparent);
        $red = imagecolorallocatealpha($image, 255, 0, 0, 0);
        imagefilledrectangle($image, 20, 20, 60, 60, $red);

        $absolute = Storage::disk('public')->path('products/alpha-source.png');
        @mkdir(dirname($absolute), 0755, true);
        imagepng($image, $absolute);
        imagedestroy($image);

        $result = app(StorefrontImageDerivativeService::class)
            ->generateFromPublicPath('products/alpha-source.png');

        $this->assertNotNull($result);
        $derivative = imagecreatefromwebp(Storage::disk('public')->path($result['path']));
        $this->assertNotFalse($derivative);

        $corner = imagecolorat($derivative, 0, 0);
        $alpha = ($corner & 0x7F000000) >> 24;
        $this->assertGreaterThan(100, $alpha, 'Transparent corner should retain high alpha.');
        imagedestroy($derivative);
    }

    public function test_deterministic_derivative_path_and_original_untouched_on_rerun(): void
    {
        Storage::fake('public');

        $upload = UploadedFile::fake()->image('repeat.jpg', 800, 600);
        $path = $upload->storeAs('products', 'repeat-stem.jpg', 'public');
        $service = app(StorefrontImageDerivativeService::class);

        $first = $service->generateFromPublicPath($path);
        $originalBytes = Storage::disk('public')->get($path);
        $second = $service->generateFromPublicPath($path);

        $this->assertSame($first['path'], $second['path']);
        $this->assertSame(
            'products/storefront/repeat-stem.webp',
            $service->derivativeRelativePath($path),
        );
        $this->assertSame($originalBytes, Storage::disk('public')->get($path));
    }

    public function test_backfill_is_idempotent_and_links_display_url(): void
    {
        Storage::fake('public');

        $upload = UploadedFile::fake()->image('backfill.jpg', 1800, 1800);
        $path = $upload->storeAs('products', 'backfill-stem.jpg', 'public');
        $url = Storage::disk('public')->url($path);

        $product = Product::factory()->create();
        $media = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => $url,
            'display_url' => null,
        ]);

        $backfill = app(StorefrontImageDerivativeBackfillService::class);

        $first = $backfill->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
        ]);
        $this->assertSame(1, $first['generated']);
        $this->assertSame(0, $first['failed']);

        $media->refresh();
        $this->assertNotNull($media->display_url);
        $this->assertTrue(
            Storage::disk('public')->exists('products/storefront/backfill-stem.webp'),
        );

        $second = $backfill->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
        ]);
        $this->assertSame(0, $second['generated']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame($media->display_url, $media->fresh()->display_url);
    }

    public function test_missing_derivative_falls_back_to_original_in_resolver_contract(): void
    {
        $product = Product::factory()->create();
        $media = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => '/storage/products/missing-derivative.jpg',
            'display_url' => null,
        ]);

        $resolved = app(\App\Services\Catalog\CustomerProductMediaResolver::class)
            ->resolvePrimary($product->fresh(['media']));

        $this->assertSame($media->url, $resolved['url']);
        $this->assertSame($media->url, $resolved['original_url']);
        $this->assertNull($resolved['display_url']);
    }
}
