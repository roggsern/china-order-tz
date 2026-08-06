<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductMedia\ProductMediaUploadContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class AdminProductMediaUploadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_constants_and_messages_match_ten_megabyte_policy(): void
    {
        $this->assertSame(10240, ProductMediaUploadContract::MAX_KILOBYTES);
        $this->assertSame(5000, ProductMediaUploadContract::MAX_WIDTH);
        $this->assertSame(5000, ProductMediaUploadContract::MAX_HEIGHT);
        $this->assertSame('jpg,jpeg,png,webp', ProductMediaUploadContract::MIMES);

        $messages = ProductMediaUploadContract::fileMessages();
        $this->assertStringContainsString('10 MB', $messages['file.max']);
        $this->assertStringContainsString('5000', $messages['file.dimensions']);
        $this->assertStringContainsString('HEIC', $messages['file.mimes']);
        $this->assertStringContainsString('HEIC', $messages['file.image']);
    }

    public function test_catalog_media_accepts_image_between_five_and_ten_megabytes(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('mid-size.jpg', 6000),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'image');
    }

    public function test_catalog_media_rejects_over_ten_megabytes_with_clear_message(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('too-big.jpg', 10241),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertStringContainsString(
            '10 MB',
            (string) $response->json('errors.file.0'),
        );
    }

    public function test_catalog_media_rejects_oversized_dimensions_clearly(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to synthesize an oversize-dimension test image.');
        }

        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('huge.jpg', 5001, 100);

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertStringContainsString(
            '5000',
            (string) $response->json('errors.file.0'),
        );
    }

    public function test_catalog_media_rejects_unsupported_format_without_accepting_heic(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $file = UploadedFile::fake()->create('photo.heic', 100, 'image/heic');

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $message = strtolower((string) $response->json('errors.file.0'));
        $this->assertTrue(
            str_contains($message, 'heic') || str_contains($message, 'jpg') || str_contains($message, 'image'),
            'Expected unsupported-format guidance, got: '.$message,
        );
    }

    public function test_small_jpeg_upload_still_succeeds(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('small.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
    }

    public function test_variant_media_uses_same_max_kilobytes_contract(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('variant-mid.jpg', 6000),
            'product_variant_id' => $variant->id,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_variant_id', $variant->id);
    }
}
