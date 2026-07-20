<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 049 — CMS Featured Content Engine.
 * Reusable featured rails for homepage, landing, campaign, and future surfaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_featured_contents')) {
            Schema::create('cms_featured_contents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('cms_homepage_section_id')
                    ->constrained('cms_homepage_sections')
                    ->cascadeOnDelete();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('source_type', 32); // MANUAL|BEST_SELLERS|...
                $table->unsignedInteger('limit')->default(8);
                $table->string('sort_order', 64)->default('default'); // resolver hint, not position
                $table->string('display_style', 32)->default('GRID'); // GRID|CAROUSEL|LIST|COMPACT
                $table->json('configuration')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->string('status', 32)->default('draft'); // draft|active|archived
                $table->boolean('is_visible')->default(true);
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index('cms_homepage_section_id');
                $table->index('source_type');
                $table->index('status');
                $table->index('is_visible');
                $table->index('position');
                $table->index(
                    ['cms_homepage_section_id', 'status', 'is_visible', 'position'],
                    'cms_featured_storefront_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_featured_contents');
    }
};
