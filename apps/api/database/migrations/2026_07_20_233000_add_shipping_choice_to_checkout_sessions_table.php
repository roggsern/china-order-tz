<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist explicit pre-payment shipping choice on checkout sessions.
 * COMPANY_SHIPPING | CUSTOMER_AGENT | SELF_PICKUP | NEGOTIATED_DELIVERY
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->string('shipping_choice', 40)->nullable()->after('grand_total');
            $table->string('shipping_method', 20)->nullable()->after('shipping_choice');
            $table->string('agent_name')->nullable()->after('shipping_method');
            $table->string('agent_contact')->nullable()->after('agent_name');
            $table->string('cart_fingerprint', 64)->nullable()->after('agent_contact');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_choice',
                'shipping_method',
                'agent_name',
                'agent_contact',
                'cart_fingerprint',
            ]);
        });
    }
};
