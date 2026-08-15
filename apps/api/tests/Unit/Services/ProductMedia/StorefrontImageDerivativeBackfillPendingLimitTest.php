<?php

namespace Tests\Unit\Services\ProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ProductMedia\StorefrontImageDerivativeBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontImageDerivativeBackfillPendingLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD + imagewebp required for storefront derivative backfill tests.');
        }
    }

    public function test_limit_excludes_completed_rows_and_selects_pending_only(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $backfill = app(StorefrontImageDerivativeBackfillService::class);

        $completed = [];
        for ($i = 0; $i < 3; $i++) {
            $path = UploadedFile::fake()->image("done-{$i}.jpg", 400, 400)
                ->storeAs('products', "done-{$i}.jpg", 'public');
            $completed[] = ProductMedia::factory()->create([
                'product_id' => $product->id,
                'url' => Storage::disk('public')->url($path),
                'display_url' => Storage::disk('public')->url("products/storefront/done-{$i}.webp"),
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $pending = [];
        for ($i = 0; $i < 5; $i++) {
            $path = UploadedFile::fake()->image("pending-{$i}.jpg", 500, 500)
                ->storeAs('products', "pending-{$i}.jpg", 'public');
            $pending[] = ProductMedia::factory()->create([
                'product_id' => $product->id,
                'url' => Storage::disk('public')->url($path),
                'display_url' => null,
                'created_at' => now()->subMinutes(5 - $i),
            ]);
        }

        $result = $backfill->backfill([
            'dry_run' => true,
            'product_id' => $product->id,
            'limit' => 5,
        ]);

        $this->assertSame(5, $result['processed']);
        $this->assertSame(5, $result['generated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(
            array_map(static fn (ProductMedia $media) => $media->id, $pending),
            array_column($result['rows'], 'media_id'),
        );
        foreach ($completed as $media) {
            $this->assertNotContains($media->id, array_column($result['rows'], 'media_id'));
        }
    }

    public function test_second_bounded_batch_advances_after_first_execute(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $backfill = app(StorefrontImageDerivativeBackfillService::class);

        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            $path = UploadedFile::fake()->image("batch-{$i}.jpg", 600, 600)
                ->storeAs('products', "batch-{$i}.jpg", 'public');
            $ids[] = ProductMedia::factory()->create([
                'product_id' => $product->id,
                'url' => Storage::disk('public')->url($path),
                'display_url' => null,
                'created_at' => now()->subMinutes(4 - $i),
            ])->id;
        }

        $first = $backfill->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
            'limit' => 2,
        ]);
        $this->assertSame(2, $first['processed']);
        $this->assertSame(2, $first['generated']);
        $this->assertSame([$ids[0], $ids[1]], array_column($first['rows'], 'media_id'));

        $second = $backfill->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
            'limit' => 2,
        ]);
        $this->assertSame(2, $second['processed']);
        $this->assertSame(2, $second['generated']);
        $this->assertSame([$ids[2], $ids[3]], array_column($second['rows'], 'media_id'));

        $third = $backfill->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
            'limit' => 2,
        ]);
        $this->assertSame(0, $third['processed']);
    }

    public function test_existing_derivative_with_null_display_url_is_linked(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $path = UploadedFile::fake()->image('link-me.jpg', 700, 700)
            ->storeAs('products', 'link-me.jpg', 'public');
        $derivative = app(\App\Services\ProductMedia\StorefrontImageDerivativeService::class)
            ->generateFromPublicPath($path);
        $this->assertNotNull($derivative);

        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url($path),
            'display_url' => null,
        ]);

        $result = app(StorefrontImageDerivativeBackfillService::class)->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
            'limit' => 1,
        ]);

        $this->assertSame(1, $result['linked_existing']);
        $this->assertSame(0, $result['generated']);
        $this->assertSame('linked', $result['rows'][0]['action']);
        $this->assertSame($derivative['url'], $media->fresh()->display_url);
    }

    public function test_missing_derivative_is_generated(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $path = UploadedFile::fake()->image('gen-me.jpg', 800, 800)
            ->storeAs('products', 'gen-me.jpg', 'public');
        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url($path),
            'display_url' => null,
        ]);

        $result = app(StorefrontImageDerivativeBackfillService::class)->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
            'limit' => 1,
        ]);

        $this->assertSame(1, $result['generated']);
        $this->assertSame(0, $result['linked_existing']);
        $this->assertSame('generated', $result['rows'][0]['action']);
        $this->assertNotNull($media->fresh()->display_url);
        $this->assertTrue(Storage::disk('public')->exists('products/storefront/gen-me.webp'));
    }

    public function test_completed_row_is_not_reprocessed(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $path = UploadedFile::fake()->image('done.jpg', 400, 400)
            ->storeAs('products', 'done.jpg', 'public');
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url($path),
            'display_url' => '/storage/products/storefront/done.webp',
        ]);

        $result = app(StorefrontImageDerivativeBackfillService::class)->backfill([
            'dry_run' => false,
            'product_id' => $product->id,
            'limit' => 5,
        ]);

        $this->assertSame(0, $result['processed']);
        $this->assertSame([], $result['rows']);
    }

    public function test_dry_run_does_not_mutate_db_or_files(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $path = UploadedFile::fake()->image('dry.jpg', 900, 900)
            ->storeAs('products', 'dry.jpg', 'public');
        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url($path),
            'display_url' => null,
        ]);

        $result = app(StorefrontImageDerivativeBackfillService::class)->backfill([
            'dry_run' => true,
            'product_id' => $product->id,
            'limit' => 1,
        ]);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['generated']);
        $this->assertSame('would_generate', $result['rows'][0]['action']);
        $this->assertNull($media->fresh()->display_url);
        $this->assertFalse(Storage::disk('public')->exists('products/storefront/dry.webp'));
    }

    public function test_product_filter_with_limit_is_respected(): void
    {
        Storage::fake('public');
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $pathA = UploadedFile::fake()->image('a.jpg', 400, 400)
            ->storeAs('products', 'only-a.jpg', 'public');
        $mediaA = ProductMedia::factory()->create([
            'product_id' => $productA->id,
            'url' => Storage::disk('public')->url($pathA),
            'display_url' => null,
        ]);

        $pathB = UploadedFile::fake()->image('b.jpg', 400, 400)
            ->storeAs('products', 'only-b.jpg', 'public');
        ProductMedia::factory()->create([
            'product_id' => $productB->id,
            'url' => Storage::disk('public')->url($pathB),
            'display_url' => null,
            'created_at' => now()->subMinute(),
        ]);

        $result = app(StorefrontImageDerivativeBackfillService::class)->backfill([
            'dry_run' => true,
            'product_id' => $productA->id,
            'limit' => 5,
        ]);

        $this->assertSame(1, $result['processed']);
        $this->assertSame([$mediaA->id], array_column($result['rows'], 'media_id'));
    }

    public function test_ordering_is_created_at_then_id(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $later = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url(
                UploadedFile::fake()->image('later.jpg', 400, 400)->storeAs('products', 'later.jpg', 'public'),
            ),
            'display_url' => null,
            'created_at' => now()->subMinute(),
        ]);
        $earlier = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url(
                UploadedFile::fake()->image('earlier.jpg', 400, 400)->storeAs('products', 'earlier.jpg', 'public'),
            ),
            'display_url' => null,
            'created_at' => now()->subMinutes(5),
        ]);

        $result = app(StorefrontImageDerivativeBackfillService::class)->backfill([
            'dry_run' => true,
            'product_id' => $product->id,
            'limit' => 2,
        ]);

        $this->assertSame([$earlier->id, $later->id], array_column($result['rows'], 'media_id'));
    }

    public function test_external_and_demo_urls_do_not_block_pending_batches(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        // Older non-actionable rows that remain display_url NULL forever.
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.com/external.jpg',
            'display_url' => null,
            'created_at' => now()->subHours(2),
        ]);
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => '/storage/demo-products/phone.jpg',
            'display_url' => null,
            'created_at' => now()->subHour(),
        ]);

        $path = UploadedFile::fake()->image('actionable.jpg', 450, 450)
            ->storeAs('products', 'actionable.jpg', 'public');
        $actionable = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => Storage::disk('public')->url($path),
            'display_url' => null,
            'created_at' => now()->subMinutes(10),
        ]);

        $result = app(StorefrontImageDerivativeBackfillService::class)->backfill([
            'dry_run' => true,
            'product_id' => $product->id,
            'limit' => 1,
        ]);

        $this->assertSame(1, $result['processed']);
        $this->assertSame([$actionable->id], array_column($result['rows'], 'media_id'));
        $this->assertSame('would_generate', $result['rows'][0]['action']);
    }
}
