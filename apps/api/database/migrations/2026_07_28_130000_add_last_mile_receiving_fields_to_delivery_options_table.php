<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_options')) {
            return;
        }

        Schema::table('delivery_options', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_options', 'last_mile_receiving_method')) {
                $table->string('last_mile_receiving_method')->nullable()->after('delivery_status');
                $table->index('last_mile_receiving_method');
            }

            if (! Schema::hasColumn('delivery_options', 'last_mile_selected_at')) {
                $table->timestamp('last_mile_selected_at')->nullable()->after('last_mile_receiving_method');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_options')) {
            return;
        }

        Schema::table('delivery_options', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_options', 'last_mile_selected_at')) {
                $table->dropColumn('last_mile_selected_at');
            }

            if (Schema::hasColumn('delivery_options', 'last_mile_receiving_method')) {
                $table->dropColumn('last_mile_receiving_method');
            }
        });
    }
};
