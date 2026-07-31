<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->foreignUuid('storefront_visitor_id')
                ->nullable()
                ->after('user_id')
                ->constrained('storefront_visitors')
                ->nullOnDelete();
            $table->foreignUuid('storefront_session_id')
                ->nullable()
                ->after('storefront_visitor_id')
                ->constrained('storefront_sessions')
                ->nullOnDelete();

            $table->index(['storefront_visitor_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('storefront_visitor_id')
                ->nullable()
                ->after('user_id')
                ->constrained('storefront_visitors')
                ->nullOnDelete();
            $table->foreignUuid('storefront_session_id')
                ->nullable()
                ->after('storefront_visitor_id')
                ->constrained('storefront_sessions')
                ->nullOnDelete();

            $table->index(['storefront_visitor_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_session_id');
            $table->dropConstrainedForeignId('storefront_visitor_id');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_session_id');
            $table->dropConstrainedForeignId('storefront_visitor_id');
        });
    }
};
