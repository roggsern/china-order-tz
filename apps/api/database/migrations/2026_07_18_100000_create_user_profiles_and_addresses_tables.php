<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: Core & Authentication — user_profiles, user_addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('phone', 20)->nullable()->index();
            $table->string('avatar')->nullable();
            $table->string('preferred_language', 10)->nullable()->default('en');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Tanzania');
            $table->boolean('is_shipping')->default(true);
            $table->boolean('is_billing')->default(false);
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
        Schema::dropIfExists('user_profiles');
    }
};
