<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 integrity indexes for catalog engines (non-breaking additive).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('product_variant_attribute_values')
            && ! $this->indexExists('product_variant_attribute_values', 'pvav_variant_attr_unique')
            && ! $this->hasDuplicateVariantAttributes()
        ) {
            Schema::table('product_variant_attribute_values', function (Blueprint $table) {
                $table->unique(
                    ['product_variant_id', 'catalog_attribute_id'],
                    'pvav_variant_attr_unique',
                );
            });
        }

        if (
            Schema::hasTable('product_variants')
            && ! $this->indexExists('product_variants', 'product_variants_product_default_idx')
        ) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->index(['product_id', 'is_default'], 'product_variants_product_default_idx');
            });
        }

        if (
            Schema::hasTable('products')
            && ! $this->indexExists('products', 'products_catalog_type_lifecycle_idx')
        ) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(
                    ['catalog_product_type_id', 'lifecycle_status', 'is_active'],
                    'products_catalog_type_lifecycle_idx',
                );
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('product_variant_attribute_values')
            && $this->indexExists('product_variant_attribute_values', 'pvav_variant_attr_unique')
        ) {
            Schema::table('product_variant_attribute_values', function (Blueprint $table) {
                $table->dropUnique('pvav_variant_attr_unique');
            });
        }

        if (
            Schema::hasTable('product_variants')
            && $this->indexExists('product_variants', 'product_variants_product_default_idx')
        ) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropIndex('product_variants_product_default_idx');
            });
        }

        if (
            Schema::hasTable('products')
            && $this->indexExists('products', 'products_catalog_type_lifecycle_idx')
        ) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_catalog_type_lifecycle_idx');
            });
        }
    }

    private function hasDuplicateVariantAttributes(): bool
    {
        return DB::table('product_variant_attribute_values')
            ->select('product_variant_id', 'catalog_attribute_id')
            ->groupBy('product_variant_id', 'catalog_attribute_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $result = $connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
