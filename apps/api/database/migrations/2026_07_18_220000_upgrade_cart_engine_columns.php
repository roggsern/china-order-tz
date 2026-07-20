<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cart Engine — currency + price_snapshot on existing carts/cart_items.
 * Dedup key becomes cart_id + product_variant_id for variant lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carts') && ! Schema::hasColumn('carts', 'currency')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->string('currency', 3)->default('TZS')->after('status');
            });
        }

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                if (! Schema::hasColumn('cart_items', 'currency')) {
                    $table->string('currency', 3)->default('TZS')->after('unit_price');
                }
                if (! Schema::hasColumn('cart_items', 'price_snapshot')) {
                    $table->decimal('price_snapshot', 18, 2)->nullable()->after('currency');
                }
            });

            // Backfill price_snapshot from unit_price.
            if (Schema::hasColumn('cart_items', 'price_snapshot')) {
                DB::table('cart_items')
                    ->whereNull('price_snapshot')
                    ->update(['price_snapshot' => DB::raw('unit_price')]);
            }

            if (
                ! $this->indexExists('cart_items', 'cart_items_cart_variant_unique')
                && ! $this->hasDuplicateCartVariants()
            ) {
                Schema::table('cart_items', function (Blueprint $table) {
                    $table->unique(
                        ['cart_id', 'product_variant_id'],
                        'cart_items_cart_variant_unique',
                    );
                });
            }
        }
    }

    private function hasDuplicateCartVariants(): bool
    {
        return DB::table('cart_items')
            ->whereNotNull('product_variant_id')
            ->whereNull('deleted_at')
            ->select('cart_id', 'product_variant_id')
            ->groupBy('cart_id', 'product_variant_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    public function down(): void
    {
        if (Schema::hasTable('cart_items')) {
            if ($this->indexExists('cart_items', 'cart_items_cart_variant_unique')) {
                Schema::table('cart_items', function (Blueprint $table) {
                    $table->dropUnique('cart_items_cart_variant_unique');
                });
            }

            Schema::table('cart_items', function (Blueprint $table) {
                if (Schema::hasColumn('cart_items', 'price_snapshot')) {
                    $table->dropColumn('price_snapshot');
                }
                if (Schema::hasColumn('cart_items', 'currency')) {
                    $table->dropColumn('currency');
                }
            });
        }

        if (Schema::hasTable('carts') && Schema::hasColumn('carts', 'currency')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            foreach ($connection->select("PRAGMA index_list('{$table}')") as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $result = $connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$connection->getDatabaseName(), $table, $indexName],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
