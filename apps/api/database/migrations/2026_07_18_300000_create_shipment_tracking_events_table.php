<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event-based Tracking Engine — append-only logistics history for shipments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipment_tracking_events')) {
            return;
        }

        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('event_type');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('event_at');
            $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('event_type');
            $table->index('event_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
