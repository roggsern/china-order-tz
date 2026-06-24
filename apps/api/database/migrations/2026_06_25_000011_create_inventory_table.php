<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->string('warehouse_location')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'product_variant_id']);
            $table->index(['quantity', 'low_stock_threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
