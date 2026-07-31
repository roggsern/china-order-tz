<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('refund_transactions', 'customer_id')) {
                $table->foreignUuid('customer_id')->nullable()->after('order_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('refund_transactions', 'payment_id')) {
                $table->foreignUuid('payment_id')->nullable()->after('customer_id')->constrained('payments')->nullOnDelete();
            }
            if (! Schema::hasColumn('refund_transactions', 'reason')) {
                $table->string('reason')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('refund_transactions', 'created_by_admin_id')) {
                $table->foreignUuid('created_by_admin_id')->nullable()->after('reason')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('refund_transactions', 'approved_by_admin_id')) {
                $table->foreignUuid('approved_by_admin_id')->nullable()->after('created_by_admin_id')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('refund_transactions', 'processed_by_admin_id')) {
                $table->foreignUuid('processed_by_admin_id')->nullable()->after('approved_by_admin_id')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('refund_transactions', 'rejected_by_admin_id')) {
                $table->foreignUuid('rejected_by_admin_id')->nullable()->after('processed_by_admin_id')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('refund_transactions', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('reference');
            }
            if (! Schema::hasColumn('refund_transactions', 'provider_response')) {
                $table->json('provider_response')->nullable()->after('provider_reference');
            }
            if (! Schema::hasColumn('refund_transactions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('provider_response');
            }
            if (! Schema::hasColumn('refund_transactions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('refund_transactions', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('refund_transactions', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('processed_at');
            }
            if (! Schema::hasColumn('refund_transactions', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('refund_transactions', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('refund_transactions', function (Blueprint $table) {
            $columns = [
                'failed_at',
                'rejected_at',
                'completed_at',
                'processed_at',
                'approved_at',
                'reviewed_at',
                'provider_response',
                'provider_reference',
                'rejected_by_admin_id',
                'processed_by_admin_id',
                'approved_by_admin_id',
                'created_by_admin_id',
                'reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('refund_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('refund_transactions', 'payment_id')) {
                $table->dropConstrainedForeignId('payment_id');
            }
            if (Schema::hasColumn('refund_transactions', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
