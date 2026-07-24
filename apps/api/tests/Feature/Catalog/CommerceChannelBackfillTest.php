<?php

namespace Tests\Feature\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\ProductChannelBackfillLog;
use App\Models\Store;
use App\Services\Catalog\CommerceChannelAuditor;
use App\Services\Catalog\CommerceChannelBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceChannelBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function legacyChinaProduct(array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'name' => 'Legacy China Product',
            'lifecycle_status' => ProductLifecycleStatus::Active,
        ], $overrides));
        $product->forceFill(['commerce_channel_id' => null])->save();

        return $product->fresh() ?? $product;
    }

    public function test_auditor_detects_missing_channels(): void
    {
        $this->legacyChinaProduct(['name' => 'Missing Channel One']);
        $this->legacyChinaProduct(['name' => 'Missing Channel Two']);

        $report = app(CommerceChannelAuditor::class)->audit();

        $this->assertSame(2, $report['total_affected']);
        $this->assertTrue(
            collect($report['products'])->pluck('name')->contains('Missing Channel One'),
        );
    }

    public function test_backfill_assigns_china_import_on_execute(): void
    {
        $china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
        $product = $this->legacyChinaProduct();

        $result = app(CommerceChannelBackfillService::class)->backfill([
            'dry_run' => false,
        ]);

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'commerce_channel_id' => $china->id,
        ]);
        $this->assertDatabaseHas('product_channel_backfill_logs', [
            'batch_id' => $result['batch_id'],
            'product_id' => $product->id,
            'previous_channel_id' => null,
            'assigned_channel_id' => $china->id,
            'action' => ProductChannelBackfillLog::ACTION_ASSIGNED,
        ]);
    }

    public function test_backfill_ignores_products_already_assigned(): void
    {
        $china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();

        Product::factory()->chinaImport()->create([
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'commerce_channel_id' => $china->id,
        ]);

        $this->legacyChinaProduct();

        $result = app(CommerceChannelBackfillService::class)->backfill([
            'dry_run' => false,
        ]);

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_rollback_restores_previous_value(): void
    {
        $product = $this->legacyChinaProduct();

        $backfill = app(CommerceChannelBackfillService::class);
        $result = $backfill->backfill(['dry_run' => false]);
        $this->assertNotNull($product->fresh()?->commerce_channel_id);

        $rollback = $backfill->rollback($result['batch_id'], dryRun: false);

        $this->assertSame(1, $rollback['restored']);
        $this->assertNull($product->fresh()?->commerce_channel_id);
        $this->assertDatabaseHas('product_channel_backfill_logs', [
            'batch_id' => $result['batch_id'],
            'product_id' => $product->id,
            'action' => ProductChannelBackfillLog::ACTION_ROLLED_BACK,
        ]);
    }

    public function test_tz_local_never_touched(): void
    {
        $store = Store::query()->create([
            'code' => 'TZBF',
            'name' => 'TZ Backfill Store',
            'slug' => 'tz-backfill-store',
            'is_active' => true,
        ]);

        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $tzProduct = Product::factory()->tzLocal()->create([
            'store_id' => $store->id,
        ]);
        $tzProduct->forceFill(['commerce_channel_id' => null])->save();

        $this->legacyChinaProduct();

        $result = app(CommerceChannelBackfillService::class)->backfill([
            'dry_run' => false,
        ]);

        $this->assertSame(1, $result['assigned']);
        $this->assertNull($tzProduct->fresh()?->commerce_channel_id);
        $this->assertDatabaseMissing('product_channel_backfill_logs', [
            'product_id' => $tzProduct->id,
            'action' => ProductChannelBackfillLog::ACTION_ASSIGNED,
        ]);
    }

    public function test_rollback_fails_for_unknown_batch(): void
    {
        $this->expectException(ValidationException::class);

        app(CommerceChannelBackfillService::class)->rollback(
            '00000000-0000-4000-8000-000000000001',
            dryRun: false,
        );
    }
}
