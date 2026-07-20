<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_media')) {
            return;
        }

        Schema::create('product_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('type')->default('image')->index();
            $table->string('url');
            $table->string('thumbnail_url')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'type']);
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
