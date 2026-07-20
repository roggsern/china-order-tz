<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_attributes')) {
            Schema::create('catalog_attributes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->default('text')->index();
                $table->string('unit')->nullable();
                $table->boolean('is_filterable')->default(false)->index();
                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('catalog_attribute_options')) {
            Schema::create('catalog_attribute_options', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('catalog_attribute_id')
                    ->constrained('catalog_attributes')
                    ->cascadeOnDelete();
                $table->string('value');
                $table->string('slug');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['catalog_attribute_id', 'slug']);
                $table->index(['catalog_attribute_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('catalog_product_type_attributes')) {
            Schema::create('catalog_product_type_attributes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('catalog_product_type_id')
                    ->constrained('catalog_product_types')
                    ->cascadeOnDelete();
                $table->foreignUuid('catalog_attribute_id')
                    ->constrained('catalog_attributes')
                    ->cascadeOnDelete();
                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(
                    ['catalog_product_type_id', 'catalog_attribute_id'],
                    'cpt_attr_unique',
                );
                $table->index(['catalog_product_type_id', 'sort_order'], 'cpt_attr_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_type_attributes');
        Schema::dropIfExists('catalog_attribute_options');
        Schema::dropIfExists('catalog_attributes');
    }
};
