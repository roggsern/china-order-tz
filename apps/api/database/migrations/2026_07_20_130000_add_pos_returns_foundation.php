<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('return_reasons')) {
            Schema::create('return_reasons', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('return_requests', function (Blueprint $table) {
            $table->string('return_number')->nullable()->unique()->after('id');
            $table->string('sales_origin', 32)->nullable()->after('order_id')->index(); // pos|online
            $table->string('return_type', 32)->nullable()->after('sales_origin'); // refund|exchange
            $table->foreignUuid('store_id')->nullable()->after('return_type')->constrained('stores')->nullOnDelete();
            $table->foreignUuid('pos_session_id')->nullable()->after('store_id')->constrained('pos_sessions')->nullOnDelete();
            $table->foreignUuid('processed_by')->nullable()->after('pos_session_id')->constrained('admins')->nullOnDelete();
            $table->foreignUuid('return_reason_id')->nullable()->after('processed_by')->constrained('return_reasons')->nullOnDelete();
            $table->foreignUuid('original_receipt_id')->nullable()->after('return_reason_id')->constrained('pos_receipts')->nullOnDelete();
            $table->string('refund_method', 64)->nullable()->after('original_receipt_id');
            $table->decimal('refund_total', 14, 2)->nullable()->after('refund_method');
            $table->json('receipt_snapshot')->nullable()->after('refund_total');
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->string('inventory_disposition', 32)->nullable()->after('condition'); // sellable|damaged|inspection
            $table->foreignUuid('exchange_variant_id')->nullable()->after('replacement_requested')
                ->constrained('product_variants')->nullOnDelete();
            $table->decimal('exchange_unit_price', 14, 2)->nullable()->after('exchange_variant_id');
        });

        // Allow walk-in POS returns (existing DBs may still have NOT NULL).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                Schema::table('return_requests', function (Blueprint $table) {
                    $table->dropForeign(['customer_id']);
                });
            } catch (\Throwable) {
                // Foreign key name may vary.
            }
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE return_requests MODIFY customer_id CHAR(36) NULL');
            Schema::table('return_requests', function (Blueprint $table) {
                $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchange_variant_id');
            $table->dropColumn(['inventory_disposition', 'exchange_unit_price']);
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
            $table->dropConstrainedForeignId('pos_session_id');
            $table->dropConstrainedForeignId('processed_by');
            $table->dropConstrainedForeignId('return_reason_id');
            $table->dropConstrainedForeignId('original_receipt_id');
            $table->dropColumn([
                'return_number',
                'sales_origin',
                'return_type',
                'refund_method',
                'refund_total',
                'receipt_snapshot',
            ]);
        });

        Schema::dropIfExists('return_reasons');
    }
};
