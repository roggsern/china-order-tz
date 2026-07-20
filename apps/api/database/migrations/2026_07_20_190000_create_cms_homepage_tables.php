<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 047 — Experience Platform CMS Core (Sprint 1).
 * Homepage layouts and sections only — orchestration layer, not a commerce engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_homepage_layouts')) {
            Schema::create('cms_homepage_layouts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('commerce_context', 32); // GLOBAL|CHINA_IMPORT|TZ_LOCAL
                $table->string('status', 32)->default('draft'); // draft|active|archived
                $table->boolean('is_default')->default(false);
                /**
                 * Race-safe default slot: equals commerce_context when is_default,
                 * null otherwise. Unique constraint ⇒ at most one default per context.
                 */
                $table->string('default_slot', 32)->nullable()->unique();
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index('commerce_context');
                $table->index('status');
                $table->index('is_default');
                $table->index(['commerce_context', 'status', 'is_default'], 'cms_layouts_storefront_lookup');
            });
        }

        if (! Schema::hasTable('cms_homepage_sections')) {
            Schema::create('cms_homepage_sections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('cms_homepage_layout_id')
                    ->constrained('cms_homepage_layouts')
                    ->cascadeOnDelete();
                $table->string('section_type', 64);
                $table->string('title')->nullable();
                $table->string('subtitle')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->boolean('is_visible')->default(true);
                $table->json('configuration')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index('cms_homepage_layout_id');
                $table->index('section_type');
                $table->index('is_visible');
                $table->index('position');
                $table->index(
                    ['cms_homepage_layout_id', 'is_visible', 'position'],
                    'cms_sections_visible_order',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_homepage_sections');
        Schema::dropIfExists('cms_homepage_layouts');
    }
};
