<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion & Discount Engine — promotions, rules, usages, order discount snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->string('type', 32); // coupon | automatic
                $table->string('discount_type', 32); // percentage | fixed_amount | free_shipping
                $table->decimal('value', 14, 2)->default(0);
                $table->string('currency', 3)->nullable();
                $table->string('status', 32)->default('draft');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('per_customer_limit')->nullable();
                $table->decimal('minimum_order_amount', 14, 2)->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'starts_at', 'ends_at']);
                $table->index('type');
            });
        }

        if (! Schema::hasTable('promotion_rules')) {
            Schema::create('promotion_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('promotion_id')->constrained('promotions')->cascadeOnDelete();
                $table->string('rule_type', 40);
                $table->json('rule_value');
                $table->timestamps();

                $table->index(['promotion_id', 'rule_type']);
            });
        }

        if (! Schema::hasTable('promotion_usages')) {
            Schema::create('promotion_usages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('promotion_id')->constrained('promotions')->cascadeOnDelete();
                $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->string('currency', 3)->default('TZS');
                $table->timestamp('used_at');
                $table->timestamps();

                $table->index(['promotion_id', 'customer_id']);
                $table->index('order_id');
                $table->index('used_at');
            });
        }

        if (! Schema::hasTable('order_discount_snapshots')) {
            Schema::create('order_discount_snapshots', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignUuid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
                $table->string('promotion_name');
                $table->string('promotion_code')->nullable();
                $table->decimal('original_amount', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('final_amount', 14, 2)->default(0);
                $table->string('currency', 3)->default('TZS');
                $table->timestamps();

                $table->index('order_id');
                $table->index('promotion_id');
            });
        }

        if (Schema::hasTable('checkout_sessions')) {
            Schema::table('checkout_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('checkout_sessions', 'promotion_id')) {
                    $table->foreignUuid('promotion_id')->nullable()->after('cart_id')->constrained('promotions')->nullOnDelete();
                }
                if (! Schema::hasColumn('checkout_sessions', 'applied_promotion_code')) {
                    $table->string('applied_promotion_code')->nullable()->after('promotion_id');
                }
                if (! Schema::hasColumn('checkout_sessions', 'discount_breakdown')) {
                    $table->json('discount_breakdown')->nullable()->after('discount_total');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('checkout_sessions')) {
            Schema::table('checkout_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('checkout_sessions', 'discount_breakdown')) {
                    $table->dropColumn('discount_breakdown');
                }
                if (Schema::hasColumn('checkout_sessions', 'applied_promotion_code')) {
                    $table->dropColumn('applied_promotion_code');
                }
                if (Schema::hasColumn('checkout_sessions', 'promotion_id')) {
                    $table->dropConstrainedForeignId('promotion_id');
                }
            });
        }

        Schema::dropIfExists('order_discount_snapshots');
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('promotion_rules');
        Schema::dropIfExists('promotions');
    }
};
