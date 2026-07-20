<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('type');
            $table->json('validation')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropColumn(['unit', 'validation']);
        });
    }
};
