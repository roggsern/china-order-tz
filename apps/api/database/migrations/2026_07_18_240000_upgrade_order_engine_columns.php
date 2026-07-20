<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order Engine — checkout session link + permanent line snapshots.
 * Evolves existing orders/order_items (no parallel tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'checkout_session_id')) {
                    $table->uuid('checkout_session_id')->nullable()->after('user_id');
                    $table->index('checkout_session_id');
                }
            });

            if (
                Schema::hasColumn('orders', 'checkout_session_id')
                && Schema::hasTable('checkout_sessions')
                && ! $this->foreignKeyExists('orders', 'orders_checkout_session_id_foreign')
            ) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->foreign('checkout_session_id')
                        ->references('id')
                        ->on('checkout_sessions')
                        ->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'product_name_snapshot')) {
                    $table->string('product_name_snapshot')->nullable()->after('product_variant_id');
                }
                if (! Schema::hasColumn('order_items', 'variant_name_snapshot')) {
                    $table->string('variant_name_snapshot')->nullable()->after('product_name_snapshot');
                }
                if (! Schema::hasColumn('order_items', 'sku_snapshot')) {
                    $table->string('sku_snapshot')->nullable()->after('variant_name_snapshot');
                }
                if (! Schema::hasColumn('order_items', 'image_snapshot')) {
                    $table->string('image_snapshot')->nullable()->after('sku_snapshot');
                }
                if (! Schema::hasColumn('order_items', 'line_total')) {
                    $table->decimal('line_total', 18, 2)->nullable()->after('unit_price');
                }
                if (! Schema::hasColumn('order_items', 'currency')) {
                    $table->string('currency', 3)->default('TZS')->after('line_total');
                }
            });

            // Backfill snapshots from legacy columns.
            if (Schema::hasColumn('order_items', 'product_name_snapshot')) {
                DB::table('order_items')
                    ->whereNull('product_name_snapshot')
                    ->update([
                        'product_name_snapshot' => DB::raw('product_name'),
                        'variant_name_snapshot' => DB::raw('variant_name'),
                        'sku_snapshot' => DB::raw('sku'),
                    ]);
            }

            if (Schema::hasColumn('order_items', 'line_total')) {
                DB::table('order_items')
                    ->whereNull('line_total')
                    ->update(['line_total' => DB::raw('total_price')]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'checkout_session_id')) {
            Schema::table('orders', function (Blueprint $table) {
                if ($this->foreignKeyExists('orders', 'orders_checkout_session_id_foreign')) {
                    $table->dropForeign(['checkout_session_id']);
                }
                $table->dropIndex(['checkout_session_id']);
                $table->dropColumn('checkout_session_id');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach ([
                    'product_name_snapshot',
                    'variant_name_snapshot',
                    'sku_snapshot',
                    'image_snapshot',
                    'line_total',
                    'currency',
                ] as $column) {
                    if (Schema::hasColumn('order_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return false;
        }

        $result = $connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.table_constraints
             WHERE constraint_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$connection->getDatabaseName(), $table, $foreignKey, 'FOREIGN KEY'],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
