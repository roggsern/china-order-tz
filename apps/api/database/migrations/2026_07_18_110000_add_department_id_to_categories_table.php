<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignUuid('department_id')
                ->nullable()
                ->after('id')
                ->constrained('departments')
                ->nullOnDelete();

            $table->index(['department_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
