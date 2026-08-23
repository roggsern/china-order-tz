<?php

namespace Tests\Feature\Cart;

use App\Enums\VariantPriceType;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCartVariantImageRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const BLACK_IMAGE = '/storage/skirts/black-s.jpg';

    private const RED_IMAGE = '/storage/skirts/red-xxl.jpg';

    private const PRODUCT_IMAGE = '/storage/skirts/product-main.jpg';

    public function test_cart_mutation_response_uses_each_selected_variant_image(): void
    {
        $user = User::factory()->create();
        $catalog = $this->configurableSkirtCatalog();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $catalog['blackS']->id,
            'quantity' => 1,
        ])->assertCreated();

        $mutation = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $catalog['redXxl']->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.items');

        $mutationItems = $mutation->json('data.items');
        $this->assertIsArray($mutationItems);

        $blackLine = $this->lineForVariant($mutationItems, $catalog['blackS']->id);
        $redLine = $this->lineForVariant($mutationItems, $catalog['redXxl']->id);

        $this->assertSame(self::BLACK_IMAGE, $this->lineImageUrl($blackLine));
        $this->assertSame(self::RED_IMAGE, $this->lineImageUrl($redLine));
        $this->assertNotSame(self::BLACK_IMAGE, self::RED_IMAGE);
        $this->assertNotSame($this->lineImageUrl($blackLine), $this->lineImageUrl($redLine));

        $get = $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        $getItems = $get->json('data.items');
        $this->assertIsArray($getItems);

        $this->assertSame(
            self::BLACK_IMAGE,
            $this->lineImageUrl($this->lineForVariant($getItems, $catalog['blackS']->id)),
        );
        $this->assertSame(
            self::RED_IMAGE,
            $this->lineImageUrl($this->lineForVariant($getItems, $catalog['redXxl']->id)),
        );
    }

    public function test_cart_mutation_falls_back_to_product_image_when_variant_has_no_media(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(18000, 20);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => self::PRODUCT_IMAGE,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.product.primary_image.url', self::PRODUCT_IMAGE)
            ->assertJsonPath('data.items.0.product.images.0.url', self::PRODUCT_IMAGE);
    }

    /**
     * @return array{product: Product, blackS: ProductVariant, redXxl: ProductVariant}
     */
    private function configurableSkirtCatalog(): array
    {
        ['product' => $product, 'variant' => $blackS] = CatalogCartFixture::purchasable(25000, 20);

        $blackS->update([
            'sku' => 'COT-TZ-ZIONMODE-7UVAFE-BLACK-S',
            'name' => 'Black S',
        ]);

        $redXxl = $this->additionalPurchasableVariant($product, [
            'sku' => 'COT-TZ-ZIONMODE-7UVAFE-RED-XXL',
            'name' => 'Red XXL',
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => self::PRODUCT_IMAGE,
        ]);
        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $blackS->id,
            'url' => self::BLACK_IMAGE,
        ]);
        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $redXxl->id,
            'url' => self::RED_IMAGE,
        ]);

        return [
            'product' => $product,
            'blackS' => $blackS->fresh(),
            'redXxl' => $redXxl,
        ];
    }

    /**
     * @param  array{sku: string, name: string}  $attributes
     */
    private function additionalPurchasableVariant(Product $product, array $attributes): ProductVariant
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => $attributes['sku'],
            'name' => $attributes['name'],
            'is_active' => true,
            'is_default' => false,
            'price' => null,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 20,
            'reserved' => 0,
            'reorder_level' => 5,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        return $variant;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function lineForVariant(array $items, string $variantId): array
    {
        foreach ($items as $item) {
            if (($item['product_variant_id'] ?? null) === $variantId) {
                return $item;
            }
        }

        $this->fail('Cart line for variant '.$variantId.' was not present.');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function lineImageUrl(array $item): ?string
    {
        $productImage = $item['product']['primary_image']['url'] ?? null;
        if (is_string($productImage) && $productImage !== '') {
            return $productImage;
        }

        $variantImage = $item['variant']['primary_image']['url'] ?? null;

        return is_string($variantImage) && $variantImage !== '' ? $variantImage : null;
    }
}
