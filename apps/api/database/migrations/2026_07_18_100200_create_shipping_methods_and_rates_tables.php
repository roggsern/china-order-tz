<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: Shipping — shipping_methods, shipping_rates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('fulfillment_source')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->decimal('base_cost', 12, 2)->default(0);
            $table->decimal('cost_per_kg', 12, 2)->nullable();
            $table->decimal('min_weight', 10, 3)->nullable();
            $table->decimal('max_weight', 10, 3)->nullable();
            $table->unsignedInteger('estimated_delivery_days')->nullable();
            $table->string('currency', 3)->default('TZS');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_until')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shipping_method_id', 'is_active']);
            $table->index(['shipping_method_id', 'min_weight', 'max_weight'], 'shipping_rates_method_weight_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_methods');
    }
};
