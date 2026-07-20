<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->decimal('expected_cash', 14, 2)->nullable()->after('opening_float');
            $table->decimal('cash_sales', 14, 2)->default(0)->after('expected_cash');
            $table->decimal('cash_refunds', 14, 2)->default(0)->after('cash_sales');
            $table->decimal('variance_amount', 14, 2)->nullable()->after('closing_cash');
            $table->string('variance_type', 32)->nullable()->after('variance_amount'); // balanced|over|short
            $table->string('variance_reason', 64)->nullable()->after('variance_type');
            $table->text('closing_notes')->nullable()->after('notes');
            $table->json('payment_breakdown')->nullable()->after('closing_notes');
            $table->unsignedInteger('transaction_count')->default(0)->after('payment_breakdown');

            $table->index(['terminal_id', 'status']);
            $table->index(['opened_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropIndex(['terminal_id', 'status']);
            $table->dropIndex(['opened_at', 'status']);
            $table->dropColumn([
                'expected_cash',
                'cash_sales',
                'cash_refunds',
                'variance_amount',
                'variance_type',
                'variance_reason',
                'closing_notes',
                'payment_breakdown',
                'transaction_count',
            ]);
        });
    }
};
