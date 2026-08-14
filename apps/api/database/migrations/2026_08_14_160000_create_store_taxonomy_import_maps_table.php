<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable China source → TZ store category identity for taxonomy import.
 *
 * Prevents duplicate TZ categories when an existing store category (seeded or
 * manual) already represents the same imported concept under a different slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_taxonomy_import_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('source_category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('target_category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'source_category_id'], 'store_taxonomy_import_source_unique');
            $table->index(['store_id', 'target_category_id'], 'store_taxonomy_import_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_taxonomy_import_maps');
    }
};
