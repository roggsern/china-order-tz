<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'catalog_product_type_id')) {
                $table->foreignUuid('catalog_product_type_id')
                    ->nullable()
                    ->constrained('catalog_product_types')
                    ->nullOnDelete();
                $table->index('catalog_product_type_id');
            }

            if (! Schema::hasColumn('products', 'visibility')) {
                $table->string('visibility')->default('public')->index();
            }

            if (! Schema::hasColumn('products', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->index();
            }
        });

        // Allow nullable SKU for Product Core (no doctrine/dbal required).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY sku VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE products ALTER COLUMN sku DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'catalog_product_type_id')) {
                $table->dropConstrainedForeignId('catalog_product_type_id');
            }

            foreach (['visibility', 'sort_order'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
