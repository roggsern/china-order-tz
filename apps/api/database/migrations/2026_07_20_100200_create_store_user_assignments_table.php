<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('assignment_type', 32)->default('permanent'); // permanent|temporary
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('assigned_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['admin_id', 'store_id']);
            $table->index(['admin_id', 'is_active']);
            $table->index(['store_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_user_assignments');
    }
};
