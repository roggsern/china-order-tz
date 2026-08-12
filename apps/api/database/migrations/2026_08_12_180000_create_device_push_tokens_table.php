<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 6A — canonical customer push-token ownership (provider-neutral).
 * Does not send push; tokens are registered for a future Expo Push provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_push_tokens')) {
            return;
        }

        Schema::create('device_push_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('push_token', 512);
            $table->string('provider', 32);
            $table->string('platform', 16);
            $table->uuid('installation_id');
            $table->string('app_version', 64)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique('push_token', 'device_push_tokens_push_token_unique');
            $table->unique('installation_id', 'device_push_tokens_installation_id_unique');
            $table->index(['user_id', 'is_active'], 'device_push_tokens_user_active_index');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_push_tokens');
    }
};
