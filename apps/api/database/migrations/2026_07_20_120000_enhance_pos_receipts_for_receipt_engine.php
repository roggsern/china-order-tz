<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_receipts', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('receipt_number');
            $table->unsignedInteger('print_count')->default(0)->after('snapshot');
            $table->timestamp('last_printed_at')->nullable()->after('print_count');
            $table->foreignUuid('last_printed_by')->nullable()->after('last_printed_at')
                ->constrained('admins')->nullOnDelete();
            $table->json('qr_payload')->nullable()->after('last_printed_by');

            $table->index(['receipt_number']);
            $table->index(['pos_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_printed_by');
            $table->dropIndex(['receipt_number']);
            $table->dropIndex(['pos_session_id', 'created_at']);
            $table->dropColumn([
                'issued_at',
                'print_count',
                'last_printed_at',
                'qr_payload',
            ]);
            // last_printed_by already dropped by dropConstrainedForeignId
        });
    }
};
