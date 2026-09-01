<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\ConfigurationPriceTier;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPurchaseQuantityRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_read_exposes_null_null_when_unrestricted(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = Product::factory()->create();

        $response = $this->getJson('/api/v1/admin/products/'.$product->id)->assertOk();
        $this->assertNull($response->json('data.minimum_order_quantity'));
        $this->assertNull($response->json('data.order_increment'));
    }

    public function test_admin_read_exposes_existing_rule_as_raw_integers(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = Product::factory()->create([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ]);

        $response = $this->getJson('/api/v1/admin/products/'.$product->id)->assertOk();
        $this->assertSame(6, $response->json('data.minimum_order_quantity'));
        $this->assertSame(3, $response->json('data.order_increment'));
        $this->assertIsInt($response->json('data.minimum_order_quantity'));
        $this->assertIsInt($response->json('data.order_increment'));
    }

    public function test_create_without_rule_stores_null_null(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $fixture = $this->chinaCatalogFixture();

        $created = $this->postJson('/api/v1/admin/products', $this->createPayload($fixture, [
            'name' => 'No Purchase Rule',
        ]))->assertCreated();

        $this->assertNull($created->json('data.minimum_order_quantity'));
        $this->assertNull($created->json('data.order_increment'));

        $product = Product::query()->findOrFail($created->json('data.id'));
        $this->assertNull($product->minimum_order_quantity);
        $this->assertNull($product->order_increment);
    }

    public function test_create_moq_only_and_moq_plus_increment(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $fixture = $this->chinaCatalogFixture();

        $moqOnly = $this->postJson('/api/v1/admin/products', $this->createPayload($fixture, [
            'name' => 'MOQ Only',
            'minimum_order_quantity' => 6,
        ]))->assertCreated();

        $this->assertSame(6, $moqOnly->json('data.minimum_order_quantity'));
        $this->assertNull($moqOnly->json('data.order_increment'));

        $both = $this->postJson('/api/v1/admin/products', $this->createPayload($fixture, [
            'name' => 'MOQ And Increment',
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ]))->assertCreated();

        $this->assertSame(6, $both->json('data.minimum_order_quantity'));
        $this->assertSame(3, $both->json('data.order_increment'));
    }

    public function test_create_tz_local_supports_the_same_fields(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $fixture = $this->tzCatalogFixture();

        $created = $this->postJson('/api/v1/admin/products', [
            'name' => 'TZ Purchase Rule',
            'catalog_product_type_id' => $fixture['catalogType']->id,
            'commerce_channel_id' => $fixture['tzChannelId'],
            'store_id' => $fixture['store']->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 15000,
            'minimum_order_quantity' => 4,
            'order_increment' => 2,
        ])->assertCreated();

        $this->assertSame(4, $created->json('data.minimum_order_quantity'));
        $this->assertSame(2, $created->json('data.order_increment'));
    }

    public function test_update_add_clear_and_preserve_rules(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct();

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 6,
        ])->assertOk()->assertJsonPath('data.minimum_order_quantity', 6);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'order_increment' => 3,
        ])->assertOk()
            ->assertJsonPath('data.minimum_order_quantity', 6)
            ->assertJsonPath('data.order_increment', 3);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'Renamed With Rule Intact',
        ])->assertOk();

        $preserved = $product->fresh();
        $this->assertSame('Renamed With Rule Intact', $preserved?->name);
        $this->assertSame(6, $preserved?->minimum_order_quantity);
        $this->assertSame(3, $preserved?->order_increment);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => null,
            'order_increment' => null,
        ])->assertOk();

        $cleared = $product->fresh();
        $this->assertNull($cleared?->minimum_order_quantity);
        $this->assertNull($cleared?->order_increment);
    }

    public function test_update_clears_increment_only_and_preserves_moq(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'order_increment' => null,
        ])->assertOk()
            ->assertJsonPath('data.minimum_order_quantity', 6)
            ->assertJsonPath('data.order_increment', null);

        $fresh = $product->fresh();
        $this->assertSame(6, $fresh?->minimum_order_quantity);
        $this->assertNull($fresh?->order_increment);
    }

    public function test_update_changes_moq_and_preserves_existing_increment(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 9,
        ])->assertOk()
            ->assertJsonPath('data.minimum_order_quantity', 9)
            ->assertJsonPath('data.order_increment', 3);

        $fresh = $product->fresh();
        $this->assertSame(9, $fresh?->minimum_order_quantity);
        $this->assertSame(3, $fresh?->order_increment);
    }

    public function test_unrelated_update_does_not_activate_a_rule(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct([
            'minimum_order_quantity' => null,
            'order_increment' => null,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'Still Unrestricted',
            'description' => 'Name-only save',
        ])->assertOk();

        $fresh = $product->fresh();
        $this->assertSame('Still Unrestricted', $fresh?->name);
        $this->assertNull($fresh?->minimum_order_quantity);
        $this->assertNull($fresh?->order_increment);
        $this->assertNotSame(0, $fresh?->minimum_order_quantity);
        $this->assertNotSame(0, $fresh?->order_increment);
    }

    public function test_blank_strings_round_trip_to_null(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => '',
            'order_increment' => '',
        ])->assertOk();

        $fresh = $product->fresh();
        $this->assertNull($fresh?->minimum_order_quantity);
        $this->assertNull($fresh?->order_increment);
    }

    public function test_zero_is_not_converted_to_null(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct();

        $response = $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['minimum_order_quantity']);

        $this->assertNotSame('purchase_quantity_unsatisfied', $response->json('code'));
        $this->assertNull($product->fresh()?->minimum_order_quantity);
    }

    #[DataProvider('invalidPurchaseQuantityProvider')]
    public function test_invalid_purchase_quantity_values_are_rejected(array $payload, array $fields): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct();

        $response = $this->putJson('/api/v1/admin/products/'.$product->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($fields);

        $this->assertNotSame('purchase_quantity_unsatisfied', $response->json('code'));
        $this->assertNull($product->fresh()?->minimum_order_quantity);
        $this->assertNull($product->fresh()?->order_increment);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidPurchaseQuantityProvider(): array
    {
        return [
            'moq zero' => [['minimum_order_quantity' => 0], ['minimum_order_quantity']],
            'moq negative' => [['minimum_order_quantity' => -1], ['minimum_order_quantity']],
            'moq decimal' => [['minimum_order_quantity' => 1.5], ['minimum_order_quantity']],
            'increment zero' => [['minimum_order_quantity' => 6, 'order_increment' => 0], ['order_increment']],
            'increment negative' => [['minimum_order_quantity' => 6, 'order_increment' => -3], ['order_increment']],
            'increment decimal' => [['minimum_order_quantity' => 6, 'order_increment' => 1.5], ['order_increment']],
            'increment without moq' => [['order_increment' => 3], ['order_increment']],
            'increment with cleared moq' => [['minimum_order_quantity' => null, 'order_increment' => 3], ['order_increment']],
        ];
    }

    public function test_create_rejects_increment_without_moq(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $fixture = $this->chinaCatalogFixture();

        $response = $this->postJson('/api/v1/admin/products', $this->createPayload($fixture, [
            'name' => 'Orphan Increment',
            'order_increment' => 3,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['order_increment']);

        $this->assertNotSame('purchase_quantity_unsatisfied', $response->json('code'));
        $this->assertNull(Product::query()->where('name', 'Orphan Increment')->first());
    }

    #[DataProvider('invalidCreatePurchaseQuantityProvider')]
    public function test_create_rejects_invalid_purchase_quantity_values(array $overrides, array $fields): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $fixture = $this->chinaCatalogFixture();

        $response = $this->postJson('/api/v1/admin/products', $this->createPayload($fixture, [
            'name' => 'Invalid Create Purchase Quantity',
            ...$overrides,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors($fields);

        $this->assertNotSame('purchase_quantity_unsatisfied', $response->json('code'));
        $this->assertNull(Product::query()->where('name', 'Invalid Create Purchase Quantity')->first());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidCreatePurchaseQuantityProvider(): array
    {
        return [
            'moq zero' => [['minimum_order_quantity' => 0], ['minimum_order_quantity']],
            'increment zero' => [['minimum_order_quantity' => 6, 'order_increment' => 0], ['order_increment']],
            'moq decimal' => [['minimum_order_quantity' => 1.5], ['minimum_order_quantity']],
            'increment decimal' => [['minimum_order_quantity' => 6, 'order_increment' => 1.5], ['order_increment']],
            'moq negative' => [['minimum_order_quantity' => -1], ['minimum_order_quantity']],
            'increment negative' => [['minimum_order_quantity' => 6, 'order_increment' => -3], ['order_increment']],
        ];
    }

    public function test_clearing_moq_while_increment_remains_is_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['order_increment']);

        $fresh = $product->fresh();
        $this->assertSame(6, $fresh?->minimum_order_quantity);
        $this->assertSame(3, $fresh?->order_increment);
    }

    public function test_allows_moq_one_with_increment_and_increment_greater_than_moq(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct();

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 1,
            'order_increment' => 3,
        ])->assertOk()
            ->assertJsonPath('data.minimum_order_quantity', 1)
            ->assertJsonPath('data.order_increment', 3);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 6,
            'order_increment' => 10,
        ])->assertOk()
            ->assertJsonPath('data.minimum_order_quantity', 6)
            ->assertJsonPath('data.order_increment', 10);
    }

    public function test_unauthorized_customer_cannot_write_fields(): void
    {
        $product = Product::factory()->create();

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 6,
        ])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 6,
        ])->assertUnauthorized();

        $this->assertNull($product->fresh()?->minimum_order_quantity);
    }

    public function test_view_only_admin_cannot_bypass_product_edit_policy(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_VIEW])->create(),
        );

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 6,
        ])->assertForbidden();

        $this->assertNull($product->fresh()?->minimum_order_quantity);
    }

    public function test_permitted_admin_can_edit_through_current_flow(): void
    {
        $product = $this->editableChinaProduct();
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
                AdminPermissions::CATALOG_UPDATE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 8,
            'order_increment' => 2,
        ])->assertOk()
            ->assertJsonPath('data.minimum_order_quantity', 8)
            ->assertJsonPath('data.order_increment', 2);
    }

    public function test_view_only_admin_cannot_create_purchase_quantity_product(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_VIEW])->create(),
        );
        $fixture = $this->chinaCatalogFixture();

        $this->postJson('/api/v1/admin/products', $this->createPayload($fixture, [
            'name' => 'View Only Create Blocked',
            'minimum_order_quantity' => 6,
        ]))->assertForbidden();

        $this->assertNull(Product::query()->where('name', 'View Only Create Blocked')->first());
    }

    public function test_create_permission_can_persist_purchase_quantity_fields(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
                AdminPermissions::CATALOG_CREATE,
            ])->create(),
        );
        $fixture = $this->chinaCatalogFixture();

        $created = $this->postJson('/api/v1/admin/products', $this->createPayload($fixture, [
            'name' => 'Create Permission Purchase Rule',
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ]))->assertCreated();

        $this->assertSame(6, $created->json('data.minimum_order_quantity'));
        $this->assertSame(3, $created->json('data.order_increment'));
    }

    public function test_updating_rule_does_not_mutate_volume_pricing_tiers(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct(['price' => 100000]);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'unit_price' => 90000,
        ]);

        $this->assertSame(1, ConfigurationPriceTier::query()->where('product_id', $product->id)->count());

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->assertOk();

        $this->assertSame(1, ConfigurationPriceTier::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('configuration_price_tiers', [
            'product_id' => $product->id,
            'min_quantity' => 10,
            'unit_price' => '90000.00',
        ]);
        $this->assertSame(6, $product->fresh()?->minimum_order_quantity);
    }

    public function test_customer_quote_and_cart_reflect_admin_rule_change(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $product = $this->editableTzProduct([
            'price' => 10000,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
        ]);
        Inventory::query()->firstOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 100, 'reserved_quantity' => 0, 'low_stock_threshold' => 2],
        );

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.purchase_quantity', null);

        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->assertOk();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/products/{$product->slug}/quote", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.purchase_quantity.minimum_quantity', 6)
            ->assertJsonPath('data.purchase_quantity.increment', 3)
            ->assertJsonPath('data.purchase_quantity.blocks_checkout', true);

        $cart = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $cart->assertJsonPath('data.items.0.purchase_quantity.minimum_quantity', 6)
            ->assertJsonPath('data.purchase_quantity_blockers.0.minimum_quantity', 6);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function editableChinaProduct(array $overrides = []): Product
    {
        $fixture = $this->chinaCatalogFixture();

        return Product::factory()->fromChina()->create(array_merge([
            'catalog_product_type_id' => $fixture['catalogType']->id,
            'category_id' => $fixture['catalogType']->subcategory_id,
            'commerce_channel_id' => $fixture['chinaChannelId'],
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function editableTzProduct(array $overrides = []): Product
    {
        $fixture = $this->tzCatalogFixture();

        return Product::factory()->tzLocal()->create(array_merge([
            'catalog_product_type_id' => $fixture['catalogType']->id,
            'category_id' => $fixture['catalogType']->subcategory_id,
            'commerce_channel_id' => $fixture['tzChannelId'],
            'store_id' => $fixture['store']->id,
        ], $overrides));
    }

    /**
     * @return array{catalogType: CatalogProductType, chinaChannelId: string}
     */
    private function chinaCatalogFixture(): array
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        return [
            'catalogType' => $catalogType,
            'chinaChannelId' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
        ];
    }

    /**
     * @return array{catalogType: CatalogProductType, tzChannelId: string, store: Store}
     */
    private function tzCatalogFixture(): array
    {
        $store = Store::query()->create([
            'code' => 'PQR1',
            'name' => 'Purchase Qty Store',
            'slug' => 'purchase-qty-store',
            'is_active' => true,
        ]);
        $root = Category::factory()->forStore($store)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forStore($store)->child($root)->create();
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        return [
            'catalogType' => $catalogType,
            'tzChannelId' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store' => $store,
        ];
    }

    /**
     * @param  array{catalogType: CatalogProductType, chinaChannelId: string}  $fixture
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function createPayload(array $fixture, array $overrides): array
    {
        return array_merge([
            'name' => 'Purchase Quantity Product',
            'catalog_product_type_id' => $fixture['catalogType']->id,
            'commerce_channel_id' => $fixture['chinaChannelId'],
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 25000,
        ], $overrides);
    }
}
