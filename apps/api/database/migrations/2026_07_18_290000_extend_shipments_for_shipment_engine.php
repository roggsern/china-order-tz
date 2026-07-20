<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsibility-aware Shipment Engine columns on existing shipments table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'fulfillment_id')) {
                $table->foreignUuid('fulfillment_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('fulfillments')
                    ->nullOnDelete();
                $table->unique('fulfillment_id');
            }

            if (! Schema::hasColumn('shipments', 'shipment_number')) {
                $table->string('shipment_number')->nullable()->unique()->after('fulfillment_id');
            }

            if (! Schema::hasColumn('shipments', 'transport_mode')) {
                $table->string('transport_mode')->nullable()->after('shipment_number')->index();
            }

            if (! Schema::hasColumn('shipments', 'carrier_name')) {
                $table->string('carrier_name')->nullable()->after('status');
            }

            if (! Schema::hasColumn('shipments', 'tracking_reference')) {
                $table->string('tracking_reference')->nullable()->after('carrier_name')->index();
            }

            if (! Schema::hasColumn('shipments', 'origin')) {
                $table->string('origin')->nullable()->after('tracking_reference');
            }

            if (! Schema::hasColumn('shipments', 'destination')) {
                $table->string('destination')->nullable()->after('origin');
            }

            if (! Schema::hasColumn('shipments', 'booked_at')) {
                $table->timestamp('booked_at')->nullable()->after('destination');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            foreach ([
                'fulfillment_id',
                'shipment_number',
                'transport_mode',
                'carrier_name',
                'tracking_reference',
                'origin',
                'destination',
                'booked_at',
            ] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    if ($column === 'fulfillment_id') {
                        $table->dropConstrainedForeignId('fulfillment_id');
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
