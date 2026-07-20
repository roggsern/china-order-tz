<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 051 — CMS Navigation Shell Engine.
 * Orchestrates primary/footer/mobile/utility chrome; does not own commerce taxonomy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_navigation_shells')) {
            Schema::create('cms_navigation_shells', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('commerce_context', 32); // GLOBAL|CHINA_IMPORT|TZ_LOCAL
                $table->string('navigation_type', 32); // PRIMARY|FOOTER|MOBILE|UTILITY
                $table->string('status', 32)->default('draft'); // draft|active|archived
                $table->boolean('is_default')->default(false);
                // Unique default per (commerce_context + navigation_type)
                $table->string('default_slot', 64)->nullable()->unique();
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index('commerce_context');
                $table->index('navigation_type');
                $table->index('status');
                $table->index('is_default');
                $table->index(['commerce_context', 'navigation_type', 'status'], 'cms_nav_shells_resolve_idx');
            });
        }

        if (! Schema::hasTable('cms_navigation_items')) {
            Schema::create('cms_navigation_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('navigation_shell_id')
                    ->constrained('cms_navigation_shells')
                    ->cascadeOnDelete();
                $table->foreignUuid('parent_id')
                    ->nullable()
                    ->constrained('cms_navigation_items')
                    ->nullOnDelete();
                $table->string('title');
                $table->string('icon')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->string('visibility', 32)->default('PUBLIC'); // PUBLIC|AUTH_ONLY|GUEST_ONLY|ADMIN_PREVIEW
                $table->string('item_type', 32); // LINK|JOURNEY|MEGA_MENU|GROUP
                $table->string('target_type', 32)->nullable(); // CmsCtaTargetType for LINK
                $table->string('target_value')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->index(['navigation_shell_id', 'position']);
                $table->index('parent_id');
                $table->index('item_type');
                $table->index('is_enabled');
            });
        }

        if (! Schema::hasTable('cms_campaign_navigation_shell')) {
            Schema::create('cms_campaign_navigation_shell', function (Blueprint $table) {
                $table->foreignUuid('cms_campaign_id')->constrained('cms_campaigns')->cascadeOnDelete();
                $table->foreignUuid('cms_navigation_shell_id')->constrained('cms_navigation_shells')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['cms_campaign_id', 'cms_navigation_shell_id'], 'cms_campaign_nav_shell_pk');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_campaign_navigation_shell');
        Schema::dropIfExists('cms_navigation_items');
        Schema::dropIfExists('cms_navigation_shells');
    }
};
