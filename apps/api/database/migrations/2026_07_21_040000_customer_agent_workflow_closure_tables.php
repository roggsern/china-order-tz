<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Launch Closure #4 — Customer Agent pickup / authorization / warehouse release.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_options', 'agent_company')) {
            Schema::table('delivery_options', function (Blueprint $table) {
                $table->string('agent_company')->nullable()->after('agent_contact');
                $table->string('agent_phone')->nullable()->after('agent_company');
                $table->string('agent_email')->nullable()->after('agent_phone');
                $table->string('pickup_reference')->nullable()->after('agent_email');
            });
        }

        if (! Schema::hasTable('customer_agent_pickups')) {
            Schema::create('customer_agent_pickups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('fulfillment_id')->nullable()->constrained('fulfillments')->nullOnDelete();
                $table->foreignUuid('warehouse_job_id')->nullable()->constrained('warehouse_jobs')->nullOnDelete();
                $table->foreignUuid('delivery_option_id')->nullable()->constrained('delivery_options')->nullOnDelete();

                $table->string('agent_name');
                $table->string('agent_company')->nullable();
                $table->string('agent_phone')->nullable();
                $table->string('agent_email')->nullable();
                $table->string('agent_contact')->nullable();
                $table->string('pickup_reference')->unique();

                $table->string('authorization_status')->default('pending');
                $table->timestamp('authorization_expires_at')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->foreignUuid('authorized_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('authorization_notes')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('revoke_reason')->nullable();

                $table->string('release_status')->nullable();
                $table->timestamp('pickup_scheduled_at')->nullable();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->foreignUuid('release_operator_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('release_notes')->nullable();

                $table->string('pickup_status')->default('awaiting_pickup');
                $table->timestamp('agent_arrived_at')->nullable();
                $table->timestamp('identity_verified_at')->nullable();
                $table->timestamp('authorization_verified_at')->nullable();
                $table->timestamp('goods_verified_at')->nullable();
                $table->timestamp('handover_completed_at')->nullable();
                $table->foreignUuid('handover_operator_id')->nullable()->constrained('admins')->nullOnDelete();

                $table->json('evidence')->nullable();
                $table->text('pickup_notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique('order_id');
                $table->index('authorization_status');
                $table->index('release_status');
                $table->index('pickup_status');
                $table->index('fulfillment_id');
            });
        }

        if (! Schema::hasTable('customer_agent_pickup_histories')) {
            Schema::create('customer_agent_pickup_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('customer_agent_pickup_id')
                    ->constrained('customer_agent_pickups')
                    ->cascadeOnDelete();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('action');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->string('idempotency_key')->nullable()->unique();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['order_id', 'action']);
                $table->index('customer_agent_pickup_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_agent_pickup_histories');
        Schema::dropIfExists('customer_agent_pickups');

        if (Schema::hasColumn('delivery_options', 'agent_company')) {
            Schema::table('delivery_options', function (Blueprint $table) {
                $table->dropColumn(['agent_company', 'agent_phone', 'agent_email', 'pickup_reference']);
            });
        }
    }
};
