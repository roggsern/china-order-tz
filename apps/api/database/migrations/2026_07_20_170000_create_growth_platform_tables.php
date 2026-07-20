<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 043 — Growth Platform & Customer Engagement Engine.
 * Orchestrates CRM / Loyalty / Promotions / Notifications — does not replace them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('preferred_currency');
            $table->string('growth_stage', 32)->nullable()->after('date_of_birth'); // new|active|vip|inactive|winback
        });

        Schema::create('growth_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('rules'); // { all: [ {field, op, value} ] }
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'store_id']);
        });

        Schema::create('growth_segment_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('growth_segment_id')->constrained('growth_segments')->cascadeOnDelete();
            $table->foreignUuid('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->timestamp('matched_at');
            $table->timestamps();

            $table->unique(['growth_segment_id', 'customer_profile_id'], 'growth_segment_member_unique');
            $table->index(['customer_profile_id']);
        });

        Schema::create('growth_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('campaign_type', 32); // promotion|announcement|new_product|retention|birthday|winback|vip
            $table->string('status', 32)->default('draft'); // draft|scheduled|running|completed|cancelled
            $table->foreignUuid('growth_segment_id')->nullable()->constrained('growth_segments')->nullOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('channel', 32)->default('whatsapp'); // primary channel
            $table->json('channels')->nullable(); // ordered channel preference
            $table->string('message_title')->nullable();
            $table->text('message_body');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->unsignedInteger('bonus_points')->nullable();
            $table->string('promotion_code')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->unsignedInteger('purchased_count')->default(0);
            $table->decimal('revenue_generated', 14, 2)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'campaign_type']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('growth_campaign_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('growth_campaign_id')->constrained('growth_campaigns')->cascadeOnDelete();
            $table->foreignUuid('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('status', 32)->default('queued'); // queued|sent|delivered|opened|clicked|redeemed|purchased|failed
            $table->foreignUuid('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['growth_campaign_id', 'customer_profile_id'], 'growth_delivery_unique');
            $table->index(['status', 'created_at']);
        });

        Schema::create('growth_journeys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type', 32); // registration|inactive_days|vip_threshold|birthday|manual
            $table->json('trigger_config')->nullable();
            $table->foreignUuid('growth_segment_id')->nullable()->constrained('growth_segments')->nullOnDelete();
            $table->foreignUuid('growth_campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'trigger_type']);
        });

        Schema::create('growth_journey_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('growth_journey_id')->constrained('growth_journeys')->cascadeOnDelete();
            $table->foreignUuid('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->string('status', 32)->default('active'); // active|completed|cancelled
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['growth_journey_id', 'customer_profile_id'], 'growth_journey_enroll_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_journey_enrollments');
        Schema::dropIfExists('growth_journeys');
        Schema::dropIfExists('growth_campaign_deliveries');
        Schema::dropIfExists('growth_campaigns');
        Schema::dropIfExists('growth_segment_members');
        Schema::dropIfExists('growth_segments');

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'growth_stage']);
        });
    }
};
