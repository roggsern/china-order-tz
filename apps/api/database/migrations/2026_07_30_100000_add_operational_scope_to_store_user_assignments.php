<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_user_assignments', function (Blueprint $table) {
            $table->string('operational_scope', 32)
                ->default('store_operator')
                ->after('assignment_type');
            $table->index(['store_id', 'operational_scope', 'is_active'], 'store_assignments_scope_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('store_user_assignments', function (Blueprint $table) {
            $table->dropIndex('store_assignments_scope_active_idx');
            $table->dropColumn('operational_scope');
        });
    }
};
