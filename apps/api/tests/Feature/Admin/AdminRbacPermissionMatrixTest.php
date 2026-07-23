<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerLifecycleStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\InventoryStockMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Crm\CustomerProfileService;
use App\Support\Admin\AdminPermissions;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RC1-E1 — Granular admin RBAC permission matrix.
 */
class AdminRbacPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_guest_and_customer_token_rejected_from_admin_mutations(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertUnauthorized();
    }

    public function test_inactive_admin_rejected_before_permission_check(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
        $admin = Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->inactive()->create();

        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertForbidden();
    }

    public function test_unauthorized_admin_cannot_mark_order_paid_and_causes_no_lifecycle_mutation(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
        $admin = Admin::factory()->withoutPermissions()->create();

        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertForbidden();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::query()->where('order_id', $order->id)->count());
    }

    public function test_authorized_admin_and_super_admin_can_mark_order_paid(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 20);

        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'variant_name_snapshot' => $variant->name,
            'sku_snapshot' => $variant->sku,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 10000,
            'line_total' => 10000,
            'total_price' => 10000,
            'currency' => 'TZS',
        ]);

        $finance = Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create();
        Sanctum::actingAs($finance);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $order2 = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
        $order2->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'variant_name_snapshot' => $variant->name,
            'sku_snapshot' => $variant->sku,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 10000,
            'line_total' => 10000,
            'total_price' => 10000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs(Admin::factory()->superAdmin()->create());
        $this->patchJson('/api/v1/admin/orders/'.$order2->id.'/pay')->assertOk();
    }

    public function test_unauthorized_admin_cannot_cancel_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
        $admin = Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create();

        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/cancel')->assertForbidden();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_unauthorized_admin_cannot_complete_or_fail_refund(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::RefundPending]);
        $admin = Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/orders/'.$order->id.'/refunds/complete', [
            'amount' => 10,
            'reference' => 'REF-1',
            'confirm' => true,
        ])->assertForbidden();

        $this->postJson('/api/v1/admin/orders/'.$order->id.'/refunds/fail', [
            'reason' => 'bank rejected',
            'confirm' => true,
        ])->assertForbidden();

        $this->assertSame(OrderStatus::RefundPending, $order->fresh()->status);
    }

    public function test_unauthorized_admin_cannot_adjust_inventory_and_writes_no_ledger(): void
    {
        $variant = ProductVariant::factory()->create();
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);
        $beforeMovements = InventoryStockMovement::query()->count();

        $admin = Admin::factory()->withPermissions([AdminPermissions::INVENTORY_VIEW])->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/inventory/'.$inventory->id, [
            'on_hand' => 99,
        ])->assertForbidden();

        $this->assertSame(10, (int) $inventory->fresh()->on_hand);
        $this->assertSame($beforeMovements, InventoryStockMovement::query()->count());
    }

    public function test_unauthorized_admin_cannot_create_update_delete_legacy_payments(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $admin = Admin::factory()->withPermissions([AdminPermissions::PAYMENTS_VIEW])->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/payments', [
            'order_id' => $order->id,
            'amount' => 1000,
            'method' => 'cash',
            'status' => 'pending',
        ])->assertForbidden();

        $this->putJson('/api/v1/admin/payments/'.$payment->id, [
            'status' => 'paid',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/admin/payments/'.$payment->id)->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_unauthorized_admin_cannot_publish_or_archive_products(): void
    {
        $product = Product::factory()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ]);
        $admin = Admin::factory()->withPermissions([AdminPermissions::CATALOG_VIEW])->create();

        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'lifecycle_status' => ProductLifecycleStatus::Active->value,
        ])->assertForbidden();

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'lifecycle_status' => ProductLifecycleStatus::Archived->value,
        ])->assertForbidden();

        $this->assertSame(ProductLifecycleStatus::Draft->value, $product->fresh()->lifecycle_status->value
            ?? (string) $product->fresh()->lifecycle_status);
    }

    public function test_unauthorized_admin_cannot_block_customers(): void
    {
        $user = User::factory()->create();
        $customerRole = Role::query()->where('slug', 'customer')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$customerRole->id]);
        $profile = app(CustomerProfileService::class)->ensureForUser($user);

        $admin = Admin::factory()->withPermissions([AdminPermissions::CUSTOMERS_VIEW])->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/admin/customers/'.$profile->id.'/status', [
            'lifecycle_status' => CustomerLifecycleStatus::Blocked->value,
            'block_reason' => 'fraud',
        ])->assertForbidden();

        $this->assertNotSame(
            CustomerLifecycleStatus::Blocked->value,
            $profile->fresh()->lifecycle_status->value ?? (string) $profile->fresh()->lifecycle_status,
        );
    }

    public function test_permissions_do_not_cross_domains_accidentally(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
        $catalogOnly = Admin::factory()->withPermissions([
            AdminPermissions::CATALOG_VIEW,
            AdminPermissions::CATALOG_CREATE,
            AdminPermissions::CATALOG_UPDATE,
        ])->create();

        Sanctum::actingAs($catalogOnly);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertForbidden();
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/cancel')->assertForbidden();
    }

    public function test_super_admin_bypass_does_not_apply_to_ordinary_admin(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

        $ordinary = Admin::factory()->ordinary()->create([
            'role_id' => Role::query()->where('slug', 'support')->value('id'),
        ]);
        $this->assertFalse($ordinary->is_super_admin);
        $this->assertFalse($ordinary->hasAdminPermission(AdminPermissions::ORDERS_MARK_PAID));

        Sanctum::actingAs($ordinary);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertForbidden();

        $super = Admin::factory()->superAdmin()->create();
        $this->assertTrue($super->hasAdminPermission(AdminPermissions::ORDERS_MARK_PAID));
        $this->assertTrue($super->hasAdminPermission(AdminPermissions::ADMINS_ASSIGN_ROLES));
    }

    public function test_authorized_admin_can_perform_only_granted_operations(): void
    {
        $ops = Admin::factory()->withPermissions([
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::ORDERS_CANCEL,
        ])->create();

        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

        Sanctum::actingAs($ops);
        $this->getJson('/api/v1/admin/orders/'.$order->id)->assertOk();
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/pay')->assertForbidden();
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/cancel')->assertOk();
    }

    public function test_customer_admin_token_isolation_remains(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/admin/orders')->assertUnauthorized();

        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create());
        $this->getJson('/api/v1/admin/orders')->assertOk();
    }

    public function test_administrator_role_receives_full_permission_matrix_without_super_flag(): void
    {
        $admin = Admin::factory()->ordinary()->create([
            'role_id' => Role::query()->where('slug', 'administrator')->value('id'),
        ]);

        $this->assertFalse($admin->is_super_admin);
        $this->assertTrue($admin->hasAdminPermission(AdminPermissions::ORDERS_MARK_PAID));
        $this->assertTrue($admin->hasAdminPermission(AdminPermissions::ADMINS_ASSIGN_ROLES));
        $this->assertTrue($admin->hasAdminPermission(AdminPermissions::INVENTORY_ADJUST));
    }

    public function test_support_role_cannot_adjust_inventory_or_manage_payments(): void
    {
        $support = Admin::factory()->ordinary()->create([
            'role_id' => Role::query()->where('slug', 'support')->value('id'),
        ]);

        $this->assertTrue($support->hasAdminPermission(AdminPermissions::CUSTOMERS_VIEW));
        $this->assertFalse($support->hasAdminPermission(AdminPermissions::INVENTORY_ADJUST));
        $this->assertFalse($support->hasAdminPermission(AdminPermissions::PAYMENTS_MANAGE_MANUAL));
        $this->assertFalse($support->hasAdminPermission(AdminPermissions::CUSTOMERS_BLOCK));
    }
}
