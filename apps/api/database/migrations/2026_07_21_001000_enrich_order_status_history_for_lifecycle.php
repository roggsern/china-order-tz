<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich order_status_history for authoritative lifecycle auditing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_history', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('notes');
            $table->string('actor_type', 20)->nullable()->after('source');
            $table->json('metadata')->nullable()->after('actor_type');
            $table->string('idempotency_key', 64)->nullable()->after('metadata');
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('order_status_history', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['source', 'actor_type', 'metadata', 'idempotency_key']);
        });
    }
};
