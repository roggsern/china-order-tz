<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost & Profit Engine — immutable cost snapshots + profit records.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_cost_snapshots')) {
            Schema::create('order_cost_snapshots', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->decimal('supplier_cost', 14, 2)->default(0);
                $table->decimal('shipping_cost', 14, 2)->default(0);
                $table->decimal('other_cost', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->string('currency', 3)->default('TZS');
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->timestamp('created_at')->useCurrent();

                $table->unique('order_item_id');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('profit_records')) {
            Schema::create('profit_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->decimal('revenue', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->decimal('gross_profit', 14, 2)->default(0);
                $table->decimal('margin_percentage', 8, 4)->default(0);
                $table->string('currency', 3)->default('TZS');
                $table->timestamp('calculated_at');
                $table->timestamps();

                $table->unique('order_id');
                $table->index('calculated_at');
                $table->index(['currency', 'calculated_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_records');
        Schema::dropIfExists('order_cost_snapshots');
    }
};
