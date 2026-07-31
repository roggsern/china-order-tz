<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fulfillment_status_histories')) {
            return;
        }

        Schema::create('fulfillment_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignUuid('changed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('source');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fulfillment_id', 'created_at']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_status_histories');
    }
};
