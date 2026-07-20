<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancellation refunds (refund_pending without a return) need RefundTransaction rows
 * that are not tied to a ReturnRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->dropForeign(['return_request_id']);
        });

        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->uuid('return_request_id')->nullable()->change();
        });

        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->foreign('return_request_id')
                ->references('id')
                ->on('return_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->dropForeign(['return_request_id']);
        });

        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->uuid('return_request_id')->nullable(false)->change();
        });

        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->foreign('return_request_id')
                ->references('id')
                ->on('return_requests')
                ->cascadeOnDelete();
        });
    }
};
