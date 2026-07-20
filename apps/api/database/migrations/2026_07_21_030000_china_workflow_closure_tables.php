<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * China Workflow Closure #3 — link POs to customer orders + specialist workflow state.
 * Does not replace OrderLifecycleEngine / Warehouse / Fulfillment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignUuid('order_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('orders')
                ->nullOnDelete();
            $table->foreignUuid('fulfillment_id')
                ->nullable()
                ->after('order_id')
                ->constrained('fulfillments')
                ->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique()->after('purchase_number');
            $table->string('supplier_response')->default('pending')->after('status');
            $table->text('supplier_response_notes')->nullable()->after('supplier_response');
            $table->timestamp('supplier_responded_at')->nullable()->after('supplier_response_notes');
            $table->index(['order_id', 'status']);
        });

        Schema::table('shipment_status_histories', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });
        Schema::table('shipment_status_histories', function (Blueprint $table) {
            $table->uuid('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
            $table->string('source')->nullable()->after('new_status');
            $table->string('idempotency_key')->nullable()->unique();
        });

        Schema::create('china_workflow_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('fulfillment_id')->nullable()->constrained('fulfillments')->nullOnDelete();
            $table->string('stage')->default('awaiting_procurement');
            $table->string('qc_status')->default('pending');
            $table->text('qc_notes')->nullable();
            $table->foreignUuid('qc_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('qc_at')->nullable();
            $table->string('consolidation_batch')->nullable();
            $table->timestamp('consolidation_completed_at')->nullable();
            $table->json('export_checklist')->nullable();
            $table->timestamp('export_ready_at')->nullable();
            $table->foreignUuid('export_approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('agent_name')->nullable();
            $table->string('agent_contact')->nullable();
            $table->text('agent_evidence')->nullable();
            $table->timestamp('agent_handed_off_at')->nullable();
            $table->foreignUuid('agent_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('china_workflow_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('china_workflow_record_id')
                ->constrained('china_workflow_records')
                ->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action');
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('china_workflow_histories');
        Schema::dropIfExists('china_workflow_records');

        Schema::table('shipment_status_histories', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['source', 'idempotency_key']);
        });
        Schema::table('shipment_status_histories', function (Blueprint $table) {
            $table->uuid('admin_id')->nullable(false)->change();
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['fulfillment_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['order_id', 'status']);
            $table->dropColumn([
                'order_id',
                'fulfillment_id',
                'idempotency_key',
                'supplier_response',
                'supplier_response_notes',
                'supplier_responded_at',
            ]);
        });
    }
};
