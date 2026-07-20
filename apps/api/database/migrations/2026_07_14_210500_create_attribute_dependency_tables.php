<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_dependencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_type_id')
                ->nullable()
                ->constrained('product_types')
                ->cascadeOnDelete();
            $table->foreignUuid('product_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignUuid('source_attribute_id')
                ->constrained('product_attributes')
                ->cascadeOnDelete();
            $table->foreignUuid('target_attribute_id')
                ->constrained('product_attributes')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index(['product_type_id', 'source_attribute_id']);
            $table->index(['product_id', 'source_attribute_id']);
        });

        Schema::create('attribute_dependency_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_dependency_id')
                ->constrained('attribute_dependencies')
                ->cascadeOnDelete();
            $table->foreignUuid('source_attribute_value_id')
                ->constrained('product_attribute_values')
                ->cascadeOnDelete();
            $table->foreignUuid('target_attribute_value_id')
                ->constrained('product_attribute_values')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['attribute_dependency_id', 'source_attribute_value_id', 'target_attribute_value_id'],
                'attr_dep_rule_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_dependency_rules');
        Schema::dropIfExists('attribute_dependencies');
    }
};
