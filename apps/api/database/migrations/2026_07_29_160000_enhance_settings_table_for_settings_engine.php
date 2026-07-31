<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-10.1B — Settings foundation engine columns on existing settings table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('type');
            $table->foreignUuid('created_by')->nullable()->after('is_active')->constrained('admins')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->after('created_by')->constrained('admins')->nullOnDelete();
            $table->index(['group', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['group', 'is_active']);
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('is_active');
        });
    }
};
