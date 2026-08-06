<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantPrice;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_active_and_trashed_lists_are_distinct(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $active = Product::factory()->tzLocal()->create(['name' => 'Active Tab Product']);
        $trashed = Product::factory()->tzLocal()->create(['name' => 'Deleted Tab Product']);
        $this->deleteJson("/api/v1/admin/products/{$trashed->id}")->assertOk();

        $activeIds = collect($this->getJson('/api/v1/admin/products?per_page=100')->assertOk()->json('data'))
            ->pluck('id');
        $trashIds = collect($this->getJson('/api/v1/admin/products?trashed=1&per_page=100')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($activeIds->contains($active->id));
        $this->assertFalse($activeIds->contains($trashed->id));
        $this->assertTrue($trashIds->contains($trashed->id));
        $this->assertFalse($trashIds->contains($active->id));
    }

    public function test_non_super_admin_without_force_delete_permission_cannot_force_delete(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::CATALOG_VIEW,
            AdminPermissions::CATALOG_DELETE,
            AdminPermissions::CATALOG_RESTORE,
        ])->create();
        Sanctum::actingAs($admin);

        $product = Product::factory()->tzLocal()->create();
        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->getJson("/api/v1/admin/products/{$product->id}/force-delete-eligibility")
            ->assertForbidden();

        $this->deleteJson("/api/v1/admin/products/{$product->id}/force", [
            'confirmation' => 'DELETE '.$product->name,
        ])->assertForbidden();
    }

    public function test_super_admin_can_force_delete_unreferenced_product(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        Storage::fake('public');

        $product = Product::factory()->tzLocal()->create(['name' => 'Force Delete Clean']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        VariantPrice::factory()->create(['product_variant_id' => $variant->id]);

        $path = 'products/force-delete-clean.jpg';
        Storage::disk('public')->put($path, 'fake-image');
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => '/storage/'.$path,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $eligibility = $this->getJson("/api/v1/admin/products/{$product->id}/force-delete-eligibility")
            ->assertOk()
            ->json('data');

        $this->assertTrue($eligibility['can_force_delete']);
        $this->assertSame('DELETE FORCE DELETE CLEAN', $eligibility['confirmation_phrase']);
        $this->assertSame(1, $eligibility['deletable_dependencies']['variants']);
        $this->assertGreaterThanOrEqual(1, $eligibility['deletable_dependencies']['variant_prices']);
        $this->assertSame(1, $eligibility['deletable_dependencies']['product_media']);

        $this->deleteJson("/api/v1/admin/products/{$product->id}/force", [
            'confirmation' => $eligibility['confirmation_phrase'],
        ])->assertOk();

        $this->assertNull(Product::withTrashed()->find($product->id));
        $this->assertNull(ProductVariant::withTrashed()->find($variant->id));
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_product_with_order_history_is_blocked_from_force_delete(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create(['name' => 'Ordered Blazer']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'Ordered Blazer',
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $eligibility = $this->getJson("/api/v1/admin/products/{$product->id}/force-delete-eligibility")
            ->assertOk()
            ->json('data');

        $this->assertFalse($eligibility['can_force_delete']);
        $this->assertSame('order_items', $eligibility['blocking_dependencies'][0]['type'] ?? null);

        $this->deleteJson("/api/v1/admin/products/{$product->id}/force", [
            'confirmation' => $eligibility['confirmation_phrase'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'product_force_delete_blocked');

        $this->assertNotNull(Product::onlyTrashed()->find($product->id));

        $item = OrderItem::query()->where('order_id', $order->id)->first();
        $this->assertSame('Ordered Blazer', $item?->product_name_snapshot);
    }

    public function test_shared_media_file_is_not_deleted(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        Storage::fake('public');

        $path = 'products/shared-force-delete.jpg';
        Storage::disk('public')->put($path, 'shared');

        $keep = Product::factory()->tzLocal()->create(['name' => 'Keep Shared Media']);
        $delete = Product::factory()->tzLocal()->create(['name' => 'Drop Shared Media']);

        ProductMedia::factory()->create([
            'product_id' => $keep->id,
            'url' => '/storage/'.$path,
        ]);
        ProductMedia::factory()->create([
            'product_id' => $delete->id,
            'url' => '/storage/'.$path,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$delete->id}")->assertOk();

        $phrase = 'DELETE DROP SHARED MEDIA';
        $this->deleteJson("/api/v1/admin/products/{$delete->id}/force", [
            'confirmation' => $phrase,
        ])->assertOk()
            ->assertJsonPath('data.media_cleanup.shared_files_skipped', 1);

        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertNotNull(Product::query()->find($keep->id));
    }

    public function test_missing_physical_file_cleanup_is_idempotent(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        Storage::fake('public');

        $product = Product::factory()->tzLocal()->create(['name' => 'Missing File Product']);
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => '/storage/products/does-not-exist.jpg',
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->deleteJson("/api/v1/admin/products/{$product->id}/force", [
            'confirmation' => 'DELETE MISSING FILE PRODUCT',
        ])
            ->assertOk()
            ->assertJsonPath('data.media_cleanup.missing_files', 1);

        $this->assertNull(Product::withTrashed()->find($product->id));
    }

    public function test_duplicate_slug_against_trashed_product_returns_422(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create([
            'name' => 'Blazer',
            'slug' => 'blazer',
        ]);
        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $payload = [
            'name' => 'Blazer',
            'slug' => 'blazer',
            'commerce_channel_id' => $product->commerce_channel_id,
            'catalog_product_type_id' => $product->catalog_product_type_id,
            'category_id' => $product->category_id,
            'price' => 1000,
            'lifecycle_status' => 'draft',
        ];

        if ($product->store_id) {
            $payload['store_id'] = $product->store_id;
        }

        $this->postJson('/api/v1/admin/products', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_slug_can_be_reused_after_permanent_deletion(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create([
            'name' => 'Reusable Slug',
            'slug' => 'reusable-slug',
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();
        $this->deleteJson("/api/v1/admin/products/{$product->id}/force", [
            'confirmation' => 'DELETE REUSABLE SLUG',
        ])->assertOk();

        $this->assertNull(Product::withTrashed()->where('slug', 'reusable-slug')->first());

        $recreated = Product::factory()->tzLocal()->create([
            'name' => 'Reusable Slug Again',
            'slug' => 'reusable-slug',
        ]);

        $this->assertSame('reusable-slug', $recreated->fresh()->slug);
    }

    public function test_force_delete_requires_exact_confirmation_phrase(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create(['name' => 'Confirm Me']);
        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->deleteJson("/api/v1/admin/products/{$product->id}/force", [
            'confirmation' => 'DELETE WRONG',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation']);

        $this->assertNotNull(Product::onlyTrashed()->find($product->id));
    }

    public function test_ordinary_admin_with_explicit_force_delete_permission_can_force_delete(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::CATALOG_VIEW,
            AdminPermissions::CATALOG_DELETE,
            AdminPermissions::CATALOG_FORCE_DELETE,
        ])->create();
        Sanctum::actingAs($admin);

        $product = Product::factory()->tzLocal()->create(['name' => 'Explicit Permission Delete']);
        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->deleteJson("/api/v1/admin/products/{$product->id}/force", [
            'confirmation' => 'DELETE EXPLICIT PERMISSION DELETE',
        ])->assertOk();

        $this->assertNull(Product::withTrashed()->find($product->id));
    }
}
