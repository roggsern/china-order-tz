<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('initiated_at')->nullable()->after('reference');
            $table->string('gateway_reference')->nullable()->after('initiated_at');
            $table->string('checkout_url')->nullable()->after('gateway_reference');
            $table->json('gateway_response')->nullable()->after('checkout_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'initiated_at',
                'gateway_reference',
                'checkout_url',
                'gateway_response',
            ]);
        });
    }
};
