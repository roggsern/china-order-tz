<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('china_commercial_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('ordered_quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id'], 'china_commercial_stocks_product_variant_unique');
        });

        Schema::create('china_procurement_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->unsignedInteger('quantity_required')->default(0);
            $table->unsignedInteger('quantity_purchased')->default(0);
            $table->string('status', 32)->default('pending');
            $table->json('variant_attributes')->nullable();
            $table->timestamps();

            $table->index(['status', 'product_id']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('china_procurement_requirement_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requirement_id')->constrained('china_procurement_requirements')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['requirement_id', 'order_item_id'], 'china_procurement_req_item_unique');
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('china_procurement_requirement_links');
        Schema::dropIfExists('china_procurement_requirements');
        Schema::dropIfExists('china_commercial_stocks');
    }
};
