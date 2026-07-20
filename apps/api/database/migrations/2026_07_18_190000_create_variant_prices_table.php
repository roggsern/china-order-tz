<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone Pricing Engine — list prices owned by product variants.
 * Distinct from product_variants.price (legacy placeholder) and configuration_price_tiers (qty breaks).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('variant_prices')) {
            return;
        }

        Schema::create('variant_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->string('price_type', 32);
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->decimal('compare_at_price', 18, 2)->nullable();
            $table->decimal('cost_price', 18, 2)->nullable();
            $table->unsignedInteger('minimum_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_variant_id');
            $table->index('currency');
            $table->index('price_type');
            $table->index(['product_variant_id', 'price_type', 'currency'], 'variant_prices_lookup_idx');
            $table->index(['is_active', 'starts_at', 'ends_at'], 'variant_prices_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
    }
};
