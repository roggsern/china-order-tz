<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout Orchestrator — session snapshot between Cart and future Order Engine.
 * Does not create orders, reserve inventory, or charge payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->string('currency', 3)->default('TZS');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('shipping_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('cart_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
