<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: coupon_usages, notification_preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->timestamp('used_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
            $table->index(['user_id', 'used_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel')->index();
            $table->string('notification_type')->index();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'channel', 'notification_type'], 'notification_prefs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('coupon_usages');
    }
};
