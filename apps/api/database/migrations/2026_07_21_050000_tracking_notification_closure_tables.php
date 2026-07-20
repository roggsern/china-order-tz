<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Launch Closure #5 — Tracking projections + notification idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_tracking_events')) {
            Schema::table('order_tracking_events', function (Blueprint $table) {
                if (! Schema::hasColumn('order_tracking_events', 'code')) {
                    $table->string('code')->nullable()->after('order_id')->index();
                }
                if (! Schema::hasColumn('order_tracking_events', 'visibility')) {
                    $table->string('visibility')->default('customer')->after('code')->index();
                }
                if (! Schema::hasColumn('order_tracking_events', 'source_module')) {
                    $table->string('source_module')->nullable()->after('visibility')->index();
                }
                if (! Schema::hasColumn('order_tracking_events', 'actor_type')) {
                    $table->string('actor_type')->nullable()->after('source_module');
                }
                if (! Schema::hasColumn('order_tracking_events', 'actor_id')) {
                    $table->uuid('actor_id')->nullable()->after('actor_type');
                }
                if (! Schema::hasColumn('order_tracking_events', 'correlation_key')) {
                    $table->string('correlation_key')->nullable()->after('actor_id');
                }
                if (! Schema::hasColumn('order_tracking_events', 'metadata')) {
                    $table->json('metadata')->nullable()->after('description');
                }
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (! Schema::hasColumn('notifications', 'idempotency_key')) {
                    $table->string('idempotency_key')->nullable()->unique();
                }
                if (! Schema::hasColumn('notifications', 'correlation_key')) {
                    $table->string('correlation_key')->nullable()->index();
                }
                if (! Schema::hasColumn('notifications', 'retry_count')) {
                    $table->unsignedSmallInteger('retry_count')->default(0);
                }
            });
        }

        if (Schema::hasTable('shipment_tracking_events')
            && ! Schema::hasColumn('shipment_tracking_events', 'idempotency_key')
        ) {
            Schema::table('shipment_tracking_events', function (Blueprint $table) {
                $table->string('idempotency_key')->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_tracking_events')) {
            Schema::table('order_tracking_events', function (Blueprint $table) {
                foreach (['code', 'visibility', 'source_module', 'actor_type', 'actor_id', 'correlation_key', 'metadata'] as $col) {
                    if (Schema::hasColumn('order_tracking_events', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                foreach (['idempotency_key', 'correlation_key', 'retry_count'] as $col) {
                    if (Schema::hasColumn('notifications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('shipment_tracking_events')
            && Schema::hasColumn('shipment_tracking_events', 'idempotency_key')
        ) {
            Schema::table('shipment_tracking_events', function (Blueprint $table) {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
