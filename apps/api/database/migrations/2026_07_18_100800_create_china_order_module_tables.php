<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: China Order Module tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('china_order_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('china_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('china_order_request_id')
                ->constrained('china_order_requests')
                ->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('specs')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['china_order_request_id', 'created_at'], 'co_items_request_created_idx');
        });

        Schema::create('china_order_source_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('china_order_request_id')
                ->constrained('china_order_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('china_order_item_id')
                ->nullable()
                ->constrained('china_order_items')
                ->nullOnDelete();
            $table->string('platform')->index();
            $table->text('url');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['china_order_request_id', 'platform'], 'co_links_request_platform_idx');
        });

        Schema::create('china_order_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('china_order_request_id')
                ->constrained('china_order_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('china_order_item_id')
                ->nullable()
                ->constrained('china_order_items')
                ->nullOnDelete();
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['china_order_request_id', 'created_at'], 'co_attach_request_created_idx');
        });

        Schema::create('china_order_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('china_order_request_id')
                ->constrained('china_order_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->decimal('product_cost', 12, 2)->nullable();
            $table->decimal('sourcing_fee', 12, 2)->nullable();
            $table->decimal('domestic_shipping', 12, 2)->nullable();
            $table->decimal('international_shipping', 12, 2)->nullable();
            $table->decimal('customs_duties', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->string('currency', 3)->default('TZS');
            $table->timestamp('valid_until')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['china_order_request_id', 'status'], 'co_quotes_request_status_idx');
        });

        Schema::create('china_order_quote_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('china_order_quote_id')
                ->constrained('china_order_quotes')
                ->cascadeOnDelete();
            $table->foreignUuid('china_order_item_id')
                ->nullable()
                ->constrained('china_order_items')
                ->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['china_order_quote_id', 'created_at'], 'co_quote_items_created_idx');
        });

        Schema::create('china_order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('china_order_request_id')
                ->constrained('china_order_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('china_order_quote_id')
                ->nullable()
                ->constrained('china_order_quotes')
                ->nullOnDelete();
            $table->foreignUuid('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['china_order_request_id', 'created_at'], 'co_status_hist_request_idx');
            $table->index(['china_order_quote_id', 'created_at'], 'co_status_hist_quote_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('china_order_status_history');
        Schema::dropIfExists('china_order_quote_items');
        Schema::dropIfExists('china_order_quotes');
        Schema::dropIfExists('china_order_attachments');
        Schema::dropIfExists('china_order_source_links');
        Schema::dropIfExists('china_order_items');
        Schema::dropIfExists('china_order_requests');
    }
};
