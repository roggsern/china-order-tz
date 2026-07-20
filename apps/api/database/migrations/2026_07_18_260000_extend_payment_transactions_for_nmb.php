<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NMB production integration fields for Payment Orchestrator transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_transactions', 'external_transaction_id')) {
                $table->string('external_transaction_id')->nullable()->after('provider_reference');
                $table->index('external_transaction_id');
            }
            if (! Schema::hasColumn('payment_transactions', 'callback_received_at')) {
                $table->timestamp('callback_received_at')->nullable()->after('initiated_at');
            }
            if (! Schema::hasColumn('payment_transactions', 'verification_payload')) {
                $table->json('verification_payload')->nullable()->after('response_payload');
            }
            if (! Schema::hasColumn('payment_transactions', 'checkout_url')) {
                $table->text('checkout_url')->nullable()->after('verification_payload');
            }
            if (! Schema::hasColumn('payment_transactions', 'success_indicator')) {
                $table->string('success_indicator')->nullable()->after('checkout_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            foreach ([
                'external_transaction_id',
                'callback_received_at',
                'verification_payload',
                'checkout_url',
                'success_indicator',
            ] as $column) {
                if (Schema::hasColumn('payment_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
