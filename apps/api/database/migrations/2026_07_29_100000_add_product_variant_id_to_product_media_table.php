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
            if (! Schema::hasColumn('product_media', 'product_variant_id')) {
                $table->foreignUuid('product_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_variants')
                    ->cascadeOnDelete();

                $table->index(['product_id', 'product_variant_id']);
                $table->index('product_variant_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_media') || ! Schema::hasColumn('product_media', 'product_variant_id')) {
            return;
        }

        Schema::table('product_media', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropIndex(['product_id', 'product_variant_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};
