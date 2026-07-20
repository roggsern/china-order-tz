<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'code']);
        });

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUuid('terminal_id')->constrained('pos_terminals')->restrictOnDelete();
            $table->foreignUuid('admin_id')->constrained('admins')->restrictOnDelete();
            $table->string('status', 32)->default('open'); // open|closed
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 14, 2)->default(0);
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['admin_id', 'status']);
        });

        Schema::create('pos_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('pos_session_id')->nullable()->constrained('pos_sessions')->nullOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_receipts');
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('pos_terminals');
    }
};
