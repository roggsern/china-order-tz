<?php

namespace Tests\Feature\Inventory;

use App\Enums\ChinaInventoryTransferStatus;
use App\Enums\InventoryWarehouseCode;
use App\Models\Admin;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\Inventory\ChinaInventoryPipeline;
use App\Services\Inventory\ChinaInventoryStockReporter;
use App\Services\Inventory\DTOs\StockResolutionContext;
use App\Services\Inventory\StockResolver;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChinaInventoryPipelineTest extends TestCase
{
    use RefreshDatabase;

    private ChinaInventoryPipeline $pipeline;

    private StockResolver $stock;

    private ChinaInventoryStockReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = app(ChinaInventoryPipeline::class);
        $this->stock = app(StockResolver::class);
        $this->reporter = app(ChinaInventoryStockReporter::class);
    }

    public function test_china_receiving_increases_china_warehouse_only(): void
    {
        ['variant' => $variant] = $this->cleanVariant();

        $transfer = $this->pipeline->receiveInChina([
            ['product_variant_id' => $variant->id, 'quantity' => 12],
        ], Admin::factory()->create());

        $this->assertSame(ChinaInventoryTransferStatus::ReceivedChina, $transfer->status);
        $this->assertSame(12, $this->onHand($variant, InventoryWarehouseCode::China));
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::Main));
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::InTransit));
    }

    public function test_export_deducts_china_and_creates_in_transit_allocation(): void
    {
        ['variant' => $variant] = $this->cleanVariant();
        $admin = Admin::factory()->create();

        $transfer = $this->pipeline->receiveInChina([
            ['product_variant_id' => $variant->id, 'quantity' => 8],
        ], $admin);
        $transfer = $this->pipeline->startQualityCheck($transfer);
        $transfer = $this->pipeline->markReadyForExport($transfer);
        $transfer = $this->pipeline->allocateShipment($transfer, $admin);

        $this->assertSame(ChinaInventoryTransferStatus::Shipment, $transfer->status);
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::China));
        $this->assertSame(8, $this->onHand($variant, InventoryWarehouseCode::InTransit));
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::Main));
    }

    public function test_arrival_tanzania_is_not_sellable_until_warehouse_receive(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->cleanVariant();
        $admin = Admin::factory()->create();

        $transfer = $this->advanceToInTransit($variant, $admin, 5);
        $transfer = $this->pipeline->markArrivedTanzania($transfer);

        $this->assertSame(ChinaInventoryTransferStatus::ArrivedTanzania, $transfer->status);
        $this->assertSame(5, $this->onHand($variant, InventoryWarehouseCode::InTransit));
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::Main));

        $commerce = $this->stock->resolveVariantProduct($variant, new StockResolutionContext, $product);
        $this->assertSame(0, $commerce->quantityAvailable);
    }

    public function test_tanzania_warehouse_receive_makes_stock_sellable(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->cleanVariant();
        $admin = Admin::factory()->create();

        $transfer = $this->advanceToInTransit($variant, $admin, 6);
        $transfer = $this->pipeline->markArrivedTanzania($transfer);
        $transfer = $this->pipeline->receiveInTanzania($transfer, $admin);

        $this->assertSame(ChinaInventoryTransferStatus::ReceivedTanzania, $transfer->status);
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::InTransit));
        $this->assertSame(6, $this->onHand($variant, InventoryWarehouseCode::Main));

        $commerce = $this->stock->resolveVariantProduct($variant, new StockResolutionContext, $product);
        $this->assertTrue($commerce->resolved);
        $this->assertSame(6, $commerce->quantityAvailable);
        $this->assertTrue($commerce->meta['sellable_for_commerce'] ?? false);
    }

    public function test_cancelled_shipment_restores_china_stock(): void
    {
        ['variant' => $variant] = $this->cleanVariant();
        $admin = Admin::factory()->create();

        $transfer = $this->advanceToInTransit($variant, $admin, 7);
        $transfer = $this->pipeline->cancelShipment($transfer, $admin);

        $this->assertSame(ChinaInventoryTransferStatus::Cancelled, $transfer->status);
        $this->assertSame(7, $this->onHand($variant, InventoryWarehouseCode::China));
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::InTransit));
        $this->assertSame(0, $this->onHand($variant, InventoryWarehouseCode::Main));
    }

    public function test_checkout_stock_resolver_ignores_china_stock(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->cleanVariant();

        $this->pipeline->receiveInChina([
            ['product_variant_id' => $variant->id, 'quantity' => 20],
        ]);

        $this->assertSame(20, $this->onHand($variant, InventoryWarehouseCode::China));

        $commerce = $this->stock->resolveVariantProduct($variant, new StockResolutionContext, $product);
        $this->assertSame(0, $commerce->quantityAvailable);
        $this->assertFalse($commerce->resolved);

        $chinaContext = StockResolutionContext::forWarehouse(InventoryWarehouseCode::China->value);
        $china = $this->stock->resolveVariantProduct($variant, $chinaContext, $product);
        $this->assertTrue($china->resolved);
        $this->assertSame(20, $china->quantityOnHand);
        $this->assertFalse($china->meta['sellable_for_commerce'] ?? true);
    }

    public function test_reporting_separates_china_tz_and_in_transit(): void
    {
        ['variant' => $a] = $this->cleanVariant();
        ['variant' => $b] = $this->cleanVariant();
        $admin = Admin::factory()->create();

        $this->pipeline->receiveInChina([
            ['product_variant_id' => $a->id, 'quantity' => 10],
        ], $admin);

        $transfer = $this->advanceToInTransit($b, $admin, 4);

        $summary = $this->reporter->summarize();
        $this->assertSame(10, $summary['china_stock']['on_hand']);
        $this->assertSame(4, $summary['in_transit_stock']['on_hand']);
        $this->assertSame(0, $summary['tz_sellable_stock']['on_hand']);

        $this->pipeline->markArrivedTanzania($transfer);
        $this->pipeline->receiveInTanzania($transfer, $admin);

        $summary = $this->reporter->summarize();
        $this->assertSame(10, $summary['china_stock']['on_hand']);
        $this->assertSame(0, $summary['in_transit_stock']['on_hand']);
        $this->assertSame(4, $summary['tz_sellable_stock']['on_hand']);

        Sanctum::actingAs(Admin::factory()->create());
        $this->getJson('/api/v1/admin/inventory/channel-stock')
            ->assertOk()
            ->assertJsonPath('data.china_stock.on_hand', 10)
            ->assertJsonPath('data.tz_sellable_stock.on_hand', 4)
            ->assertJsonPath('data.in_transit_stock.on_hand', 0);
    }

    /**
     * @return array{product: \App\Models\Product, variant: ProductVariant}
     */
    private function cleanVariant(): array
    {
        $fixture = CatalogCartFixture::purchasable(15000, onHand: 0);
        VariantInventory::withTrashed()
            ->where('product_variant_id', $fixture['variant']->id)
            ->forceDelete();

        return $fixture;
    }

    private function advanceToInTransit(ProductVariant $variant, Admin $admin, int $qty): \App\Models\ChinaInventoryTransfer
    {
        $transfer = $this->pipeline->receiveInChina([
            ['product_variant_id' => $variant->id, 'quantity' => $qty],
        ], $admin);
        $transfer = $this->pipeline->startQualityCheck($transfer);
        $transfer = $this->pipeline->markReadyForExport($transfer);
        $transfer = $this->pipeline->allocateShipment($transfer, $admin);

        return $this->pipeline->markInTransit($transfer);
    }

    private function onHand(ProductVariant $variant, InventoryWarehouseCode $warehouse): int
    {
        return (int) (VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_code', $warehouse->value)
            ->value('on_hand') ?? 0);
    }
}
