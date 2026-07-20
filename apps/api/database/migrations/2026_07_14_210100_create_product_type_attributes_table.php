<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_type_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_type_id')->constrained('product_types')->cascadeOnDelete();
            $table->foreignUuid('product_attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            /** When true, values participate in Product Configuration generation. */
            $table->boolean('participates_in_configuration')->default(true);
            $table->timestamps();

            $table->unique(['product_type_id', 'product_attribute_id'], 'product_type_attribute_unique');
            $table->index(['product_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_type_attributes');
    }
};
