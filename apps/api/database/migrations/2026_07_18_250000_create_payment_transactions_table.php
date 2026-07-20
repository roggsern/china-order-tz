<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment Orchestrator — provider-agnostic payment transactions.
 * Separate from legacy `payments` rows used by prepare/initiate/webhook flows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('provider_reference')->nullable();
            $table->string('merchant_reference')->unique();
            $table->string('currency', 3)->default('TZS');
            $table->decimal('amount', 18, 2);
            $table->string('status', 32)->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('provider');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
