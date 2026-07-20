<?php

namespace Tests\Feature\Pos;

use App\Enums\ActivityEventType;
use App\Enums\CommerceChannelCode;
use App\Enums\PosPaymentHandler;
use App\Enums\VariantPriceType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\PaymentMethodDefinition;
use App\Models\PosReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosReceiptEngineTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->stores = app(StoreService::class);
        $this->assignments = app(StoreAssignmentService::class);

        $this->tz = CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'description' => 'Local', 'is_active' => true],
        );

        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'CASH'],
            [
                'name' => 'Cash',
                'is_active' => true,
                'sort_order' => 1,
                'config' => [
                    'handler' => PosPaymentHandler::CashWithChange->value,
                    'pos_enabled' => true,
                ],
            ],
        );
        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'MPESA_LIPA'],
            [
                'name' => 'M-Pesa',
                'is_active' => true,
                'sort_order' => 2,
                'config' => [
                    'handler' => PosPaymentHandler::ManualConfirm->value,
                    'pos_enabled' => true,
                ],
            ],
        );
    }

    private function cashierFor(\App\Models\Store $store): Admin
    {
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'name' => 'John Cashier',
        ]);
        $this->assignments->assign($cashier, $store, $super);

        return $cashier;
    }

    private function seedSku(string $storeId, float $price = 50000): array
    {
        $product = Product::factory()->create([
            'store_id' => $storeId,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'is_active' => true,
            'is_demo' => false,
            'name' => 'Black Dress',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Size M',
            'price' => $price,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $price,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        $store = \App\Models\Store::query()->findOrFail($storeId);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => 20,
            'reserved' => 0,
            'is_active' => true,
        ]);

        return compact('product', 'variant');
    }

    private function openAndSell(Admin $cashier, \App\Models\Store $store, array $sku, array $saleExtra = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();

        return $this->postJson('/api/v1/admin/pos/sales', array_merge([
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 50000,
        ], $saleExtra));
    }

    public function test_receipt_generated_after_sale_with_enterprise_number_and_branding(): void
    {
        $store = $this->stores->create([
            'code' => 'ZION',
            'name' => 'Zion Mode',
            'theme_color' => '#1F4B3A',
            'settings' => [
                'receipt' => [
                    'address' => 'Samora Ave, Dar es Salaam',
                    'phone' => '+255700000001',
                    'footer_message' => 'Zion Mode — style with purpose',
                    'thank_you_message' => 'Asante sana!',
                    'return_policy' => '7 days with receipt',
                ],
            ],
        ]);
        $cashier = $this->cashierFor($store);
        $sku = $this->seedSku($store->id);

        $sale = $this->openAndSell($cashier, $store, $sku)->assertCreated();
        $receiptNumber = $sale->json('data.receipt.receipt_number');

        $this->assertMatchesRegularExpression('/^ZION-\d{4}-\d{6}$/', $receiptNumber);
        $sale->assertJsonPath('data.receipt.snapshot.branding.store_name', 'Zion Mode')
            ->assertJsonPath('data.receipt.snapshot.store.address', 'Samora Ave, Dar es Salaam')
            ->assertJsonPath('data.receipt.snapshot.messages.thank_you', 'Asante sana!')
            ->assertJsonPath('data.receipt.snapshot.order.grand_total', '50000.00')
            ->assertJsonPath('data.receipt.snapshot.payment.method_code', 'CASH')
            ->assertJsonPath('data.receipt.snapshot.payment.change', '0.00')
            ->assertJsonPath('data.receipt.snapshot.customer.is_walk_in', true)
            ->assertJsonPath('data.receipt.qr_payload.type', 'pos_receipt');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosReceiptGenerated->value,
            'subject_id' => $sale->json('data.receipt.id'),
        ]);
    }

    public function test_crm_customer_and_payment_display_on_receipt(): void
    {
        $store = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $cashier = $this->cashierFor($store);
        $sku = $this->seedSku($store->id, 25000);
        $customer = User::factory()->create(['name' => 'Amina Buyer']);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 10000,
        ])->assertCreated();

        $sale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 2,
            ]],
            'payment_method' => 'MPESA_LIPA',
            'manual_confirmed' => true,
            'customer_id' => $customer->id,
        ])->assertCreated();

        $sale->assertJsonPath('data.receipt.snapshot.customer.name', 'Amina Buyer')
            ->assertJsonPath('data.receipt.snapshot.customer.is_walk_in', false)
            ->assertJsonPath('data.receipt.snapshot.order.grand_total', '50000.00')
            ->assertJsonPath('data.receipt.snapshot.payment.method_code', 'MPESA_LIPA');

        $this->assertMatchesRegularExpression('/^TZUR-\d{4}-\d{6}$/', $sale->json('data.receipt.receipt_number'));
    }

    public function test_receipt_number_uniqueness_and_search(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashierFor($store);
        $sku = $this->seedSku($store->id, 10000);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 5000,
        ])->assertCreated();

        $first = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 10000,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 10000,
        ])->assertCreated();

        $this->assertNotSame(
            $first->json('data.receipt.receipt_number'),
            $second->json('data.receipt.receipt_number'),
        );

        $number = $first->json('data.receipt.receipt_number');
        $this->getJson('/api/v1/admin/pos/receipts?receipt_number='.$number)
            ->assertOk()
            ->assertJsonPath('data.0.receipt_number', $number);

        $orderNumber = $first->json('data.order.order_number');
        $this->getJson('/api/v1/admin/pos/orders/'.$first->json('data.order.id').'/receipt')
            ->assertOk()
            ->assertJsonPath('data.receipt_number', $number);

        $this->getJson('/api/v1/admin/pos/receipts?order_number='.$orderNumber)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_reprint_does_not_create_new_order_and_audits(): void
    {
        $store = $this->stores->create(['code' => 'PEACHY', 'name' => 'Peachy']);
        $cashier = $this->cashierFor($store);
        $sku = $this->seedSku($store->id, 15000);
        $sale = $this->openAndSell($cashier, $store, $sku, [
            'amount_received' => 20000,
        ])->assertCreated();

        $receiptId = $sale->json('data.receipt.id');
        $orderId = $sale->json('data.order.id');
        $ordersBefore = \App\Models\Order::query()->count();
        $receiptsBefore = PosReceipt::query()->count();

        $reprint = $this->postJson("/api/v1/admin/pos/receipts/{$receiptId}/reprint", [
            'layout' => 'thermal_80',
        ])->assertOk();

        $reprint->assertJsonPath('data.receipt.print_count', 1)
            ->assertJsonPath('data.layout', 'thermal_80');
        $this->assertStringContainsString('Receipt', $reprint->json('data.html'));
        $this->assertStringContainsString('PEACHY', $reprint->json('data.html'));

        $this->assertSame($ordersBefore, \App\Models\Order::query()->count());
        $this->assertSame($receiptsBefore, PosReceipt::query()->count());
        $this->assertSame($orderId, PosReceipt::query()->findOrFail($receiptId)->order_id);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosReceiptReprinted->value,
            'subject_id' => $receiptId,
        ]);
    }

    public function test_thermal_layout_and_pdf_generation(): void
    {
        $store = $this->stores->create(['code' => 'ROVI', 'name' => 'Rovi Beauty']);
        $cashier = $this->cashierFor($store);
        $sku = $this->seedSku($store->id, 8000);
        $sale = $this->openAndSell($cashier, $store, $sku, [
            'amount_received' => 10000,
        ])->assertCreated();

        $receiptId = $sale->json('data.receipt.id');

        $preview = $this->get("/api/v1/admin/pos/receipts/{$receiptId}/preview?layout=thermal_80");
        $preview->assertOk();
        $this->assertStringContainsString('text/html', (string) $preview->headers->get('Content-Type'));
        $this->assertStringContainsString('302px', $preview->getContent());
        $this->assertStringContainsString('Rovi Beauty', $preview->getContent());

        $a4 = $this->get("/api/v1/admin/pos/receipts/{$receiptId}/preview?layout=a4");
        $a4->assertOk();
        $this->assertStringContainsString('720px', $a4->getContent());

        $pdf = $this->get("/api/v1/admin/pos/receipts/{$receiptId}/pdf");
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->postJson("/api/v1/admin/pos/receipts/{$receiptId}/print", [
            'layout' => 'a4',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosReceiptPrinted->value,
            'subject_id' => $receiptId,
        ]);
    }
}
