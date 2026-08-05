<?php

namespace Tests\Feature\Console;

use App\Enums\SettingType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CmsNavigationShell;
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
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\Production\CommerceDataCleanupManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionCleanupCommerceDataCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        Artisan::call('list', ['--raw' => true]);

        $this->assertStringContainsString('production:cleanup-commerce-data', Artisan::output());
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $fixture = $this->seedCommerceFixture();

        $this->artisan('production:cleanup-commerce-data --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Customer users to delete: 1')
            ->expectsOutputToContain('products');

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Admin::query()->count());
        $this->assertTrue(Storage::disk('public')->exists($fixture['product_media_path']));
        $this->assertTrue(Storage::disk('public')->exists($fixture['cms_media_path']));
    }

    public function test_missing_force_blocks_execution(): void
    {
        $this->seedCommerceFixture();

        $this->artisan('production:cleanup-commerce-data')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('--force');

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_wrong_confirmation_phrase_blocks_execution(): void
    {
        $this->seedCommerceFixture();

        $this->artisan('production:cleanup-commerce-data', [
            '--force' => true,
            '--confirm' => 'WRONG_PHRASE',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Destructive execution blocked');

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_force_without_confirm_blocks_execution(): void
    {
        $this->seedCommerceFixture();

        $this->artisan('production:cleanup-commerce-data --force')
            ->assertFailed()
            ->expectsOutputToContain('Destructive execution blocked');

        $this->assertSame(1, Product::query()->count());
    }

    public function test_cleanup_removes_commerce_preserves_foundation_and_media_files(): void
    {
        $fixture = $this->seedCommerceFixture();

        $adminsBefore = Admin::query()->count();
        $storesBefore = Store::query()->count();
        $departmentsBefore = Department::query()->count();
        $categoriesBefore = Category::query()->count();
        $typesBefore = CatalogProductType::query()->count();
        $attrsBefore = CatalogAttribute::query()->count();
        $templatesBefore = NotificationTemplate::query()->count();
        $settingsBefore = Setting::query()->count();
        $cmsBefore = CmsNavigationShell::query()->count();
        $cmsMediaBefore = Media::query()->count();

        $this->artisan('production:cleanup-commerce-data', [
            '--force' => true,
            '--confirm' => CommerceDataCleanupManifest::CONFIRMATION_PHRASE,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Commerce cleanup completed');

        // Deleted commerce
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, ProductVariant::query()->count());
        $this->assertSame(0, DB::table('product_media')->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, Wishlist::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, PaymentTransaction::query()->count());
        $this->assertSame(0, DB::table('refunds')->count());
        $this->assertSame(0, Fulfillment::query()->count());
        $this->assertSame(0, Shipment::query()->count());
        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(0, Review::query()->count());
        $this->assertSame(0, SupportTicket::query()->count());
        $this->assertSame(0, SupportMessage::query()->count());
        $this->assertSame(0, DB::table('storefront_events')->count());

        // Preserved foundation
        $this->assertSame($adminsBefore, Admin::query()->count());
        $this->assertSame($storesBefore, Store::query()->count());
        $this->assertSame($departmentsBefore, Department::query()->count());
        $this->assertSame($categoriesBefore, Category::query()->count());
        $this->assertSame($typesBefore, CatalogProductType::query()->count());
        $this->assertSame($attrsBefore, CatalogAttribute::query()->count());
        $this->assertSame($templatesBefore, NotificationTemplate::query()->count());
        $this->assertSame($settingsBefore, Setting::query()->count());
        $this->assertSame($cmsBefore, CmsNavigationShell::query()->count());
        $this->assertSame($cmsMediaBefore, Media::query()->count());
        $this->assertDatabaseHas('admins', ['id' => $fixture['admin_id']]);
        $this->assertDatabaseHas('stores', ['id' => $fixture['store_id']]);

        // Product media file removed; CMS asset preserved
        $this->assertFalse(Storage::disk('public')->exists($fixture['product_media_path']));
        $this->assertTrue(Storage::disk('public')->exists($fixture['cms_media_path']));
    }

    public function test_command_is_idempotent(): void
    {
        $this->seedCommerceFixture();

        $this->artisan('production:cleanup-commerce-data', [
            '--force' => true,
            '--confirm' => CommerceDataCleanupManifest::CONFIRMATION_PHRASE,
        ])->assertSuccessful();

        $this->artisan('production:cleanup-commerce-data', [
            '--force' => true,
            '--confirm' => CommerceDataCleanupManifest::CONFIRMATION_PHRASE,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Commerce cleanup completed');

        $this->artisan('production:cleanup-commerce-data --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Customer users to delete: 0');

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertGreaterThanOrEqual(1, Admin::query()->count());
    }

    public function test_failure_rolls_back_database_changes(): void
    {
        $this->seedCommerceFixture();
        config(['testing.fail_commerce_cleanup_after' => 'orders']);

        $this->artisan('production:cleanup-commerce-data', [
            '--force' => true,
            '--confirm' => CommerceDataCleanupManifest::CONFIRMATION_PHRASE,
        ])
            ->assertFailed()
            ->expectsOutputToContain('aborted and rolled back');

        // Orders are deleted after many children; if transaction rolled back, commerce still present.
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Admin::query()->count());
    }

    /**
     * @return array{
     *     admin_id: string,
     *     store_id: string,
     *     product_media_path: string,
     *     cms_media_path: string
     * }
     */
    private function seedCommerceFixture(): array
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
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);
        CatalogAttribute::factory()->create();

        Setting::query()->create([
            'key' => 'cleanup.test.setting',
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

        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $productMediaPath = 'products/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($productMediaPath, 'product-bytes');
        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'url' => '/storage/'.$productMediaPath,
            'thumbnail_url' => null,
        ]);

        $order = Order::factory()->create(['user_id' => $customer->id]);
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
            'store_id' => $store->id,
            'product_media_path' => $productMediaPath,
            'cms_media_path' => $cmsPath,
            'catalog_type_id' => $catalogType->id,
        ];
    }
}
