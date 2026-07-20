<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVariantInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_variant_inventory_engine(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->assertTrue(Schema::hasTable('variant_inventories'));

        $product = Product::factory()->create([
            'name' => 'Inventory Demo Phone',
            'slug' => 'inventory-demo-phone',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => '256GB Black',
            'sku' => 'INV-DEMO-256',
            'is_default' => true,
        ]);

        $this->getJson('/api/v1/admin/variants/'.$variant->id.'/inventory')
            ->assertOk()
            ->assertJsonPath('data', []);

        $created = $this->postJson('/api/v1/admin/variants/'.$variant->id.'/inventory', [
            'warehouse_code' => 'main',
            'on_hand' => 40,
            'reserved' => 5,
            'reorder_level' => 10,
            'safety_stock' => 2,
            'is_active' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.warehouse_code', 'MAIN')
            ->assertJsonPath('data.on_hand', 40)
            ->assertJsonPath('data.reserved', 5)
            ->assertJsonPath('data.available', 35)
            ->assertJsonPath('data.needs_reorder', false);

        $inventoryId = $created->json('data.id');

        $this->postJson('/api/v1/admin/variants/'.$variant->id.'/inventory', [
            'warehouse_code' => 'MAIN',
            'on_hand' => 10,
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/variants/'.$variant->id.'/inventory', [
            'warehouse_code' => 'DSM',
            'on_hand' => 8,
            'reserved' => 12,
        ])->assertStatus(422);

        $inventory = VariantInventory::query()->findOrFail($inventoryId);
        $this->assertSame(35, $inventory->available());
        $this->assertFalse($inventory->needsReorder());
        $this->assertTrue($variant->fresh()->inventories()->exists());

        $reserved = $this->putJson('/api/v1/admin/inventory/'.$inventoryId, [
            'reserve' => 10,
        ]);
        $reserved->assertOk()
            ->assertJsonPath('data.reserved', 15)
            ->assertJsonPath('data.available', 25);

        $this->putJson('/api/v1/admin/inventory/'.$inventoryId, [
            'reserve' => 100,
        ])->assertStatus(422);

        $released = $this->putJson('/api/v1/admin/inventory/'.$inventoryId, [
            'release' => 5,
        ]);
        $released->assertOk()
            ->assertJsonPath('data.reserved', 10)
            ->assertJsonPath('data.available', 30);

        $low = $this->putJson('/api/v1/admin/inventory/'.$inventoryId, [
            'on_hand' => 12,
            'reserved' => 4,
            'reorder_level' => 10,
        ]);
        $low->assertOk()
            ->assertJsonPath('data.available', 8)
            ->assertJsonPath('data.needs_reorder', true);

        $this->assertTrue($inventory->fresh()->needsReorder());

        $deactivated = $this->putJson('/api/v1/admin/inventory/'.$inventoryId, [
            'is_active' => false,
        ]);
        $deactivated->assertOk()->assertJsonPath('data.is_active', false);

        $this->putJson('/api/v1/admin/inventory/'.$inventoryId, [
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.is_active', true);

        $this->deleteJson('/api/v1/admin/inventory/'.$inventoryId)->assertOk();
        $this->assertSoftDeleted('variant_inventories', ['id' => $inventoryId]);

        $this->postJson('/api/v1/admin/variants/'.$variant->id.'/inventory', [
            'on_hand' => -1,
        ])->assertStatus(422);
    }
}
