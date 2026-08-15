<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_media')) {
            return;
        }

        Schema::table('product_media', function (Blueprint $table) {
            if (! Schema::hasColumn('product_media', 'display_url')) {
                $table->string('display_url')->nullable()->after('thumbnail_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_media') || ! Schema::hasColumn('product_media', 'display_url')) {
            return;
        }

        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn('display_url');
        });
    }
};
