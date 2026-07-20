<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_product_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcategory_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subcategory_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_types');
    }
};
