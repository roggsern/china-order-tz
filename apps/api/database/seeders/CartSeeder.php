<?php

namespace Database\Seeders;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Illuminate\Database\Seeder;

/**
 * Demo carts for Cart Engine — lines always attach to product variants
 * with retail VariantPrice + MAIN VariantInventory.
 */
class CartSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->limit(3)->get();

        if ($users->isEmpty()) {
            $users = User::factory()->count(2)->create();
        }

        $variants = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('prices', fn ($q) => $q->where('price_type', 'retail')->where('is_active', true))
            ->whereHas('inventories', fn ($q) => $q->where('warehouse_code', 'MAIN')->where('is_active', true))
            ->with(['prices', 'inventories', 'product'])
            ->limit(12)
            ->get();

        if ($variants->isEmpty()) {
            $this->command?->warn('CartSeeder skipped: no variants with retail price + MAIN inventory.');

            return;
        }

        foreach ($users as $index => $user) {
            $cart = Cart::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'status' => CartStatus::Active,
                ],
                [
                    'session_id' => null,
                    'currency' => 'TZS',
                ],
            );

            if ($cart->items()->exists()) {
                continue;
            }

            $slice = $variants->slice($index * 2, 2);
            if ($slice->isEmpty()) {
                $slice = $variants->take(2);
            }

            foreach ($slice as $variant) {
                /** @var VariantPrice|null $retail */
                $retail = $variant->prices
                    ->first(function ($price) {
                        $type = $price->price_type;

                        return ($type?->value ?? $type) === 'retail';
                    });

                /** @var VariantInventory|null $inventory */
                $inventory = $variant->inventories
                    ->first(fn ($row) => $row->warehouse_code === 'MAIN');

                if ($retail === null || $inventory === null || $inventory->available() < 1) {
                    continue;
                }

                $qty = min(2, max(1, (int) floor($inventory->available() / 4)));

                $cart->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $qty,
                    'unit_price' => $retail->amount,
                    'price_snapshot' => $retail->amount,
                    'currency' => $retail->currency ?? 'TZS',
                ]);
            }
        }
    }
}
