<?php

namespace Tests\Feature\Console;

use App\Enums\CustomerRegistrationSource;
use App\Enums\SettingType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CmsNavigationShell;
use App\Models\CustomerProfile;
use App\Models\Department;
use App\Models\Fulfillment;
use App\Models\Media;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\Production\CustomerOrderDataCleanupManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionCleanupCustomerOrderDataCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        Artisan::call('list', ['--raw' => true]);

        $this->assertStringContainsString('production:cleanup-customer-order-data', Artisan::output());
    }

    public function test_manifest_excludes_catalog_tables(): void
    {
        $overlap = array_intersect(
            CustomerOrderDataCleanupManifest::DELETION_ORDER,
            CustomerOrderDataCleanupManifest::FORBIDDEN_DELETE_TABLES,
        );

        $this->assertSame([], array_values($overlap));
    }

    public function test_dry_run_makes_no_writes(): void
    {
        $fixture = $this->seedFixture();

        $this->artisan('production:cleanup-customer-order-data --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Customer users to delete: 1')
            ->expectsOutputToContain('PROVENANCE')
            ->expectsOutputToContain($fixture['customer_email']);

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, ProductVariant::query()->count());
        $this->assertSame(1, VariantPrice::query()->count());
        $this->assertSame(1, VariantInventory::query()->count());
        $this->assertSame(1, ProductMedia::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertTrue(Storage::disk('public')->exists($fixture['product_media_path']));
    }

    public function test_missing_force_blocks_execution(): void
    {
        $this->seedFixture();

        $this->artisan('production:cleanup-customer-order-data')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('--force');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Product::query()->count());
    }

    public function test_wrong_confirmation_phrase_blocks_execution(): void
    {
        $this->seedFixture();

        $this->artisan('production:cleanup-customer-order-data', [
            '--force' => true,
            '--confirm' => 'WRONG_PHRASE',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Destructive execution blocked');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Product::query()->count());
    }

    public function test_force_without_confirm_blocks_execution(): void
    {
        $this->seedFixture();

        $this->artisan('production:cleanup-customer-order-data --force')
            ->assertFailed()
            ->expectsOutputToContain('Destructive execution blocked');

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_cleanup_deletes_customers_orders_preserves_catalog(): void
    {
        $fixture = $this->seedFixture();

        $productsBefore = Product::withTrashed()->count();
        $variantsBefore = ProductVariant::withTrashed()->count();
        $pricesBefore = VariantPrice::query()->count();
        $inventoryBefore = VariantInventory::query()->count();
        $mediaBefore = ProductMedia::withTrashed()->count();
        $adminsBefore = Admin::query()->count();
        $categoriesBefore = Category::query()->count();

        $exit = Artisan::call('production:cleanup-customer-order-data', [
            '--force' => true,
            '--confirm' => CustomerOrderDataCleanupManifest::CONFIRMATION_PHRASE,
        ]);
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('Customer/order cleanup completed', $output);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, PaymentTransaction::query()->count());
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, Wishlist::query()->count());
        $this->assertSame(0, Fulfillment::query()->count());
        $this->assertSame(0, Shipment::query()->count());
        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(0, Review::query()->count());
        $this->assertSame(0, SupportTicket::query()->count());
        $this->assertSame(0, DB::table('refunds')->count());

        $this->assertSame($productsBefore, Product::withTrashed()->count());
        $this->assertSame($variantsBefore, ProductVariant::withTrashed()->count());
        $this->assertSame($pricesBefore, VariantPrice::query()->count());
        $this->assertSame($inventoryBefore, VariantInventory::query()->count());
        $this->assertSame($mediaBefore, ProductMedia::withTrashed()->count());
        $this->assertSame($adminsBefore, Admin::query()->count());
        $this->assertSame($categoriesBefore, Category::query()->count());
        $this->assertDatabaseHas('products', ['id' => $fixture['product_id']]);
        $this->assertDatabaseHas('product_variants', ['id' => $fixture['variant_id']]);
        $this->assertDatabaseHas('admins', ['id' => $fixture['admin_id']]);
        $this->assertTrue(Storage::disk('public')->exists($fixture['product_media_path']));
        $this->assertTrue(Storage::disk('public')->exists($fixture['cms_media_path']));
    }

    public function test_command_is_idempotent(): void
    {
        $this->seedFixture();

        $this->artisan('production:cleanup-customer-order-data', [
            '--force' => true,
            '--confirm' => CustomerOrderDataCleanupManifest::CONFIRMATION_PHRASE,
        ])->assertSuccessful();

        $this->artisan('production:cleanup-customer-order-data', [
            '--force' => true,
            '--confirm' => CustomerOrderDataCleanupManifest::CONFIRMATION_PHRASE,
        ])->assertSuccessful();

        $this->artisan('production:cleanup-customer-order-data --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Customer users to delete: 0');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, Product::query()->count());
        $this->assertGreaterThanOrEqual(1, Admin::query()->count());
    }

    public function test_failure_rolls_back_database_changes(): void
    {
        $this->seedFixture();
        config(['testing.fail_customer_order_cleanup_after' => 'orders']);

        $this->artisan('production:cleanup-customer-order-data', [
            '--force' => true,
            '--confirm' => CustomerOrderDataCleanupManifest::CONFIRMATION_PHRASE,
        ])
            ->assertFailed()
            ->expectsOutputToContain('aborted and rolled back');

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Admin::query()->count());
    }

    public function test_provenance_reports_registration_source_without_guessing(): void
    {
        $fixture = $this->seedFixture();

        $this->artisan('production:cleanup-customer-order-data --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('proven_path=customer_registration')
            ->expectsOutputToContain('registration_source=self_registration')
            ->expectsOutputToContain($fixture['customer_email']);
    }

    /**
     * @return array<string, string>
     */
    private function seedFixture(): array
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $store = Store::query()->create([
            'code' => 'ZION',
            'name' => 'Zion Mode',
            'slug' => 'zion-mode',
            'is_active' => true,
        ]);

        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create([
            'parent_id' => null,
            'store_id' => $store->id,
        ]);
        CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);
        CatalogAttribute::factory()->create();

        Setting::query()->create([
            'key' => 'cleanup.customer-order.test',
            'value' => 'keep-me',
            'group' => 'system',
            'type' => SettingType::String->value,
            'is_active' => true,
        ]);
        NotificationTemplate::factory()->create();
        CmsNavigationShell::factory()->create();

        $cmsPath = 'cms/hero/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($cmsPath, 'cms-bytes');
        Media::factory()->create([
            'disk' => 'public',
            'path' => $cmsPath,
            'filename' => basename($cmsPath),
        ]);

        $customer = User::factory()->create([
            'email' => 'sepprisegetsfashion@gmail.com',
        ]);
        CustomerProfile::query()->create([
            'user_id' => $customer->id,
            'customer_code' => 'CUST-TEST-001',
            'registration_source' => CustomerRegistrationSource::SelfRegistration,
        ]);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
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
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $productMediaPath = 'products/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($productMediaPath, 'product-bytes');
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => '/storage/'.$productMediaPath,
            'thumbnail_url' => null,
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'COTZ-20260805-000002',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'product_slug_snapshot' => $product->slug,
            'sku_snapshot' => $product->sku,
            'brand_name_snapshot' => null,
            'variant_name_snapshot' => $variant->name,
            'variant_sku_snapshot' => $variant->sku,
            'currency_snapshot' => 'TZS',
            'unit_price_snapshot' => $product->price,
            'shipping_mode_snapshot' => 'air',
            'shipping_price_snapshot' => 0,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => $product->price,
            'line_total' => $product->price,
            'total_price' => $product->price,
            'currency' => 'TZS',
            'shipping_method' => 'air',
            'shipping_price' => 0,
            'shipping_subtotal' => 0,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
        ]);
        PaymentTransaction::factory()->create(['order_id' => $order->id]);
        DB::table('refunds')->insert([
            'id' => (string) Str::uuid(),
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'pending',
            'reason' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fulfillment = Fulfillment::factory()->create(['order_id' => $order->id]);
        Shipment::factory()->create([
            'order_id' => $order->id,
            'fulfillment_id' => $fulfillment->id,
        ]);

        $cart = Cart::factory()->create(['user_id' => $customer->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);
        CheckoutSession::factory()->create([
            'user_id' => $customer->id,
            'cart_id' => $cart->id,
        ]);

        $wishlist = Wishlist::factory()->create(['user_id' => $customer->id]);
        WishlistItem::query()->create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
        ]);

        Review::factory()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        Notification::factory()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'ST-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'subject' => 'Test ticket',
            'category' => SupportTicketCategory::General->value,
            'priority' => SupportTicketPriority::Normal->value,
            'status' => SupportTicketStatus::New->value,
        ]);
        SupportMessage::query()->create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'message' => 'Hello support',
        ]);

        if (DB::getSchemaBuilder()->hasTable('storefront_visitors')) {
            $visitorId = (string) Str::uuid();
            $now = now();
            DB::table('storefront_visitors')->insert([
                'id' => $visitorId,
                'visitor_uuid' => (string) Str::uuid(),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sessionId = (string) Str::uuid();
            DB::table('storefront_sessions')->insert([
                'id' => $sessionId,
                'visitor_id' => $visitorId,
                'user_id' => $customer->id,
                'started_at' => $now,
                'last_activity_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('storefront_events')->insert([
                'id' => (string) Str::uuid(),
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'user_id' => $customer->id,
                'event_type' => 'page_view',
                'path' => '/products/demo',
                'created_at' => $now,
            ]);
        }

        return [
            'admin_id' => $admin->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'customer_email' => $customer->email,
            'product_media_path' => $productMediaPath,
            'cms_media_path' => $cmsPath,
        ];
    }
}
