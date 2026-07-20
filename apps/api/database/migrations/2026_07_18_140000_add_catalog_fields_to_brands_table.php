<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'banner')) {
                $table->string('banner')->nullable()->after('logo');
            }

            if (! Schema::hasColumn('brands', 'country')) {
                $table->string('country')->nullable()->after('website');
            }

            if (! Schema::hasColumn('brands', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->index()->after('description');
            }

            if (! Schema::hasColumn('brands', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->index()->after('is_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            foreach (['banner', 'country', 'is_featured', 'sort_order'] as $column) {
                if (Schema::hasColumn('brands', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
