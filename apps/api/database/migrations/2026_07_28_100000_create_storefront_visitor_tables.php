<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_visitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('visitor_uuid')->unique();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index('last_seen_at');
        });

        Schema::create('storefront_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('visitor_id')->constrained('storefront_visitors')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamps();

            $table->index(['visitor_id', 'ended_at']);
            $table->index(['user_id', 'last_activity_at']);
            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_sessions');
        Schema::dropIfExists('storefront_visitors');
    }
};
