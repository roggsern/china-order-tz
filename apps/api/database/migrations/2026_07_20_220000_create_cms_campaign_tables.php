<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 050 — CMS Campaign Experience Engine.
 * Storefront orchestration layer; does not replace GrowthCampaign.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_campaigns')) {
            Schema::create('cms_campaigns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('commerce_context', 32); // GLOBAL|CHINA_IMPORT|TZ_LOCAL
                $table->string('status', 32)->default('draft'); // draft|active|archived
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('priority')->default(0);
                $table->boolean('is_default')->default(false);
                $table->string('default_slot', 32)->nullable()->unique();
                $table->foreignUuid('cms_homepage_layout_id')
                    ->nullable()
                    ->constrained('cms_homepage_layouts')
                    ->nullOnDelete();
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index('commerce_context');
                $table->index('status');
                $table->index('priority');
                $table->index('is_default');
                $table->index(['commerce_context', 'status', 'priority'], 'cms_campaigns_storefront_idx');
                $table->index(['starts_at', 'ends_at']);
            });
        }

        if (! Schema::hasTable('cms_campaign_hero_slide')) {
            Schema::create('cms_campaign_hero_slide', function (Blueprint $table) {
                $table->foreignUuid('cms_campaign_id')->constrained('cms_campaigns')->cascadeOnDelete();
                $table->foreignUuid('cms_hero_slide_id')->constrained('cms_hero_slides')->cascadeOnDelete();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->primary(['cms_campaign_id', 'cms_hero_slide_id']);
                $table->index(['cms_campaign_id', 'position']);
            });
        }

        if (! Schema::hasTable('cms_campaign_featured_content')) {
            Schema::create('cms_campaign_featured_content', function (Blueprint $table) {
                $table->foreignUuid('cms_campaign_id')->constrained('cms_campaigns')->cascadeOnDelete();
                $table->foreignUuid('cms_featured_content_id')->constrained('cms_featured_contents')->cascadeOnDelete();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->primary(['cms_campaign_id', 'cms_featured_content_id'], 'cms_campaign_featured_pk');
                $table->index(['cms_campaign_id', 'position']);
            });
        }

        if (! Schema::hasTable('cms_campaign_promotion')) {
            Schema::create('cms_campaign_promotion', function (Blueprint $table) {
                $table->foreignUuid('cms_campaign_id')->constrained('cms_campaigns')->cascadeOnDelete();
                $table->foreignUuid('promotion_id')->constrained('promotions')->cascadeOnDelete();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->primary(['cms_campaign_id', 'promotion_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_campaign_promotion');
        Schema::dropIfExists('cms_campaign_featured_content');
        Schema::dropIfExists('cms_campaign_hero_slide');
        Schema::dropIfExists('cms_campaigns');
    }
};
