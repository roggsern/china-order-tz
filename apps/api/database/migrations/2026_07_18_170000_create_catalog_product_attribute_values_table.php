<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog product specification values.
 * Distinct from config-engine product_attribute_values (variant/SKU attributes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalog_product_attribute_values')) {
            return;
        }

        Schema::create('catalog_product_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignUuid('catalog_attribute_id')
                ->constrained('catalog_attributes')
                ->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 16, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->foreignUuid('option_id')
                ->nullable()
                ->constrained('catalog_attribute_options')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('catalog_attribute_id');
            $table->index(['product_id', 'catalog_attribute_id'], 'cpav_product_attr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_attribute_values');
    }
};
