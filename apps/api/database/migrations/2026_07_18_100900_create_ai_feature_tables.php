<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: AI Features —
 * ai_search_logs, product_embeddings, ai_recommendations, ai_image_search_sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_search_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('query');
            $table->string('locale', 10)->nullable();
            $table->unsignedInteger('result_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['created_at']);
        });

        Schema::create('product_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('embedding_model')->nullable()->index();
            $table->longText('embedding');
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'embedding_model']);
            $table->index(['product_id', 'updated_at']);
        });

        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('recommendation_type')->index();
            $table->json('product_ids');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'recommendation_type']);
        });

        Schema::create('ai_image_search_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('image_path');
            $table->string('status')->default('pending')->index();
            $table->json('results')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_image_search_sessions');
        Schema::dropIfExists('ai_recommendations');
        Schema::dropIfExists('product_embeddings');
        Schema::dropIfExists('ai_search_logs');
    }
};
