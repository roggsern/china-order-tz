<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product Variants Engine (catalog-driven attribute combinations).
 *
 * Extends product_variants with default/sort metadata.
 * Adds product_variant_attribute_values for catalog attribute selections.
 * Distinct from config-engine pivot product_variant_attribute_value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_variants', 'is_default')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active')->index();
            });
        }

        if (! Schema::hasColumn('product_variants', 'sort_order')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_default');
            });
        }

        if (! Schema::hasTable('product_variant_attribute_values')) {
            Schema::create('product_variant_attribute_values', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_variant_id')
                    ->constrained('product_variants')
                    ->cascadeOnDelete();
                $table->foreignUuid('catalog_attribute_id')
                    ->constrained('catalog_attributes')
                    ->cascadeOnDelete();
                $table->foreignUuid('option_id')
                    ->nullable()
                    ->constrained('catalog_attribute_options')
                    ->nullOnDelete();
                $table->text('value_text')->nullable();
                $table->decimal('value_number', 16, 4)->nullable();
                $table->boolean('value_boolean')->nullable();
                $table->timestamps();

                $table->index('product_variant_id', 'pvav_catalog_variant_idx');
                $table->index('catalog_attribute_id', 'pvav_catalog_attr_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_values');

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('product_variants', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });
    }
};
