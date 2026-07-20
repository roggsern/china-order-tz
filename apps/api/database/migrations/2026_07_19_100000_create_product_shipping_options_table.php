<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product Shipping Options Engine — manually entered air/sea prices per product.
 * System never calculates shipping costs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_shipping_options')) {
            return;
        }

        Schema::create('product_shipping_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('transport_mode');
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('is_available')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('transport_mode');
            $table->index('is_available');
            $table->unique(['product_id', 'transport_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_shipping_options');
    }
};
