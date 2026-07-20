<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 041 — Loyalty & Customer Rewards Platform.
 * Independent rewards layer; does not replace CRM or Promotion engines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('min_lifetime_points')->default(0);
            $table->decimal('min_lifetime_spend', 14, 2)->default(0);
            $table->unsignedInteger('min_orders')->default(0);
            $table->decimal('earn_multiplier', 8, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('benefits')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_profile_id')->unique()->constrained('customer_profiles')->cascadeOnDelete();
            $table->string('loyalty_number', 32)->unique();
            $table->foreignUuid('loyalty_tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
            $table->string('status', 32)->default('active'); // active|suspended|closed
            $table->unsignedInteger('points_balance')->default(0);
            $table->unsignedInteger('lifetime_points')->default(0);
            $table->unsignedInteger('lifetime_redeemed')->default(0);
            $table->timestamp('tier_updated_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'loyalty_tier_id']);
        });

        Schema::create('loyalty_earn_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('rule_type', 32); // spend|product|category|promotion_bonus
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->decimal('spend_amount', 14, 2)->nullable(); // e.g. 1000 TZS
            $table->unsignedInteger('points_awarded')->default(0);
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUuid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->unsignedInteger('bonus_points')->nullable();
            $table->unsignedInteger('expiry_months')->nullable(); // points expire after N months
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'rule_type', 'priority']);
        });

        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('reward_type', 32); // discount_voucher|free_product|special_offer|vip_benefit
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('points_cost')->default(0);
            $table->string('discount_type', 32)->nullable(); // percentage|fixed_amount
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->json('channels')->nullable(); // pos, storefront
            $table->json('config')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'reward_type']);
        });

        Schema::create('loyalty_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $table->string('entry_type', 32); // earn|redeem|expire|adjust
            $table->integer('points'); // signed: +earn, -redeem
            $table->unsignedInteger('balance_after');
            $table->string('reason')->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUuid('loyalty_earn_rule_id')->nullable()->constrained('loyalty_earn_rules')->nullOnDelete();
            $table->foreignUuid('loyalty_reward_id')->nullable()->constrained('loyalty_rewards')->nullOnDelete();
            $table->foreignUuid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('actor_type', 32)->nullable(); // system|admin|customer
            $table->uuid('actor_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['loyalty_account_id', 'created_at']);
            $table->index(['entry_type', 'created_at']);
            $table->index(['order_id']);
            $table->index(['expires_at', 'expired_at']);
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $table->foreignUuid('loyalty_reward_id')->constrained('loyalty_rewards')->restrictOnDelete();
            $table->foreignUuid('loyalty_ledger_entry_id')->nullable()->constrained('loyalty_ledger_entries')->nullOnDelete();
            $table->foreignUuid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('promotion_code', 64)->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('channel', 32)->default('pos'); // pos|storefront
            $table->string('status', 32)->default('issued'); // issued|applied|cancelled
            $table->unsignedInteger('points_spent');
            $table->timestamp('issued_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['loyalty_account_id', 'status']);
            $table->index(['promotion_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
        Schema::dropIfExists('loyalty_ledger_entries');
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('loyalty_earn_rules');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_tiers');
    }
};
