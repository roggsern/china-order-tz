<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: Catalog — category_product pivot, inventory_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->uuid('category_id');
            $table->uuid('product_id');
            $table->timestamps();

            $table->primary(['category_id', 'product_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('quantity_change');
            $table->integer('quantity_after')->nullable();
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->foreignUuid('performed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('category_product');
    }
};
