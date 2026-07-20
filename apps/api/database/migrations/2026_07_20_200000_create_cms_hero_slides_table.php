<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 048 — CMS Hero Experience Engine.
 * First-class hero slides under HERO homepage sections.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_hero_slides')) {
            Schema::create('cms_hero_slides', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('cms_homepage_section_id')
                    ->constrained('cms_homepage_sections')
                    ->cascadeOnDelete();
                $table->string('name');
                $table->string('headline');
                $table->string('subheadline')->nullable();
                $table->string('eyebrow_text')->nullable();
                $table->text('description')->nullable();
                $table->foreignUuid('desktop_media_id')->nullable()->constrained('media')->nullOnDelete();
                $table->foreignUuid('mobile_media_id')->nullable()->constrained('media')->nullOnDelete();
                $table->string('content_alignment', 16)->default('CENTER'); // LEFT|CENTER|RIGHT
                $table->string('text_theme', 16)->default('LIGHT'); // LIGHT|DARK|AUTO
                $table->string('primary_cta_label')->nullable();
                $table->string('primary_cta_type', 32)->nullable();
                $table->string('primary_cta_value', 2048)->nullable();
                $table->string('secondary_cta_label')->nullable();
                $table->string('secondary_cta_type', 32)->nullable();
                $table->string('secondary_cta_value', 2048)->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->string('status', 32)->default('draft'); // draft|active|archived
                $table->boolean('is_visible')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index('cms_homepage_section_id');
                $table->index('position');
                $table->index('status');
                $table->index('is_visible');
                $table->index('starts_at');
                $table->index('ends_at');
                $table->index(
                    ['cms_homepage_section_id', 'status', 'is_visible', 'position'],
                    'cms_hero_slides_storefront_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_hero_slides');
    }
};
