<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise Audit & Activity Log Platform — append-only activity_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type')->index();
            $table->string('action');
            $table->string('actor_type')->index();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('subject_type')->nullable()->index();
            $table->uuid('subject_id')->nullable()->index();
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
