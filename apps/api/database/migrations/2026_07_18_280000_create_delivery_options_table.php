<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery Option Engine — how a paid order leaves our responsibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_options')) {
            return;
        }

        Schema::create('delivery_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('delivery_type');
            $table->string('shipping_method')->nullable(); // air | sea (company_shipping only)
            $table->string('delivery_status')->default('pending');
            $table->string('agent_name')->nullable();
            $table->string('agent_contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('order_id');
            $table->index('delivery_type');
            $table->index('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_options');
    }
};
