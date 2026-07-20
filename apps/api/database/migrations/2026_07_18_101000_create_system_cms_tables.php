<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: System & CMS —
 * settings, pages, media, audit_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->nullable()->index();
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename')->nullable();
            $table->string('mime')->nullable()->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('alt_text')->nullable();
            $table->nullableUuidMorphs('mediable');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['disk', 'path']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->nullableUuidMorphs('auditable');
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('media');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
    }
};
