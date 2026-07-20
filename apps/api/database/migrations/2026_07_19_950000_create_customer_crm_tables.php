<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Management & CRM — profiles, metrics, tags, notes, timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_code_sequences')) {
            Schema::create('customer_code_sequences', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
                $table->unsignedBigInteger('last_value')->default(0);
                $table->timestamps();
            });

            DB::table('customer_code_sequences')->insert([
                'id' => 1,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('customer_code', 32)->unique();
                $table->string('registration_source', 40)->default('self_registration');
                $table->string('lifecycle_status', 20)->default('active');
                $table->timestamp('blocked_at')->nullable();
                $table->foreignUuid('blocked_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('block_reason')->nullable();
                $table->string('preferred_language', 16)->nullable();
                $table->string('preferred_currency', 3)->nullable();
                $table->boolean('marketing_opt_in')->default(false);
                $table->string('notes_summary', 500)->nullable();
                $table->timestamps();

                $table->index('lifecycle_status');
                $table->index('registration_source');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('customer_metrics')) {
            Schema::create('customer_metrics', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('customer_profile_id')->unique()->constrained('customer_profiles')->cascadeOnDelete();
                $table->unsignedInteger('total_orders')->default(0);
                $table->unsignedInteger('completed_orders')->default(0);
                $table->unsignedInteger('cancelled_orders')->default(0);
                $table->decimal('total_spend', 14, 2)->default(0);
                $table->decimal('total_refunds', 14, 2)->default(0);
                $table->decimal('gross_profit_generated', 14, 2)->default(0);
                $table->decimal('average_order_value', 14, 2)->default(0);
                $table->timestamp('first_order_at')->nullable();
                $table->timestamp('last_order_at')->nullable();
                $table->timestamp('last_payment_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->string('currency', 3)->default('TZS');
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->index('last_order_at');
                $table->index('total_spend');
                $table->index('total_orders');
            });
        }

        if (! Schema::hasTable('customer_tags')) {
            Schema::create('customer_tags', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_profile_tag')) {
            Schema::create('customer_profile_tag', function (Blueprint $table) {
                $table->foreignUuid('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
                $table->foreignUuid('customer_tag_id')->constrained('customer_tags')->cascadeOnDelete();
                $table->foreignUuid('assigned_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('assigned_at')->useCurrent();

                $table->unique(['customer_profile_id', 'customer_tag_id'], 'customer_profile_tag_unique');
                $table->index('customer_tag_id');
            });
        }

        if (! Schema::hasTable('customer_notes')) {
            Schema::create('customer_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('body');
                $table->boolean('is_pinned')->default(false);
                $table->timestamps();

                $table->index(['customer_profile_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('customer_timeline_events')) {
            Schema::create('customer_timeline_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
                $table->string('event_type', 64);
                $table->string('subject_type')->nullable();
                $table->uuid('subject_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['customer_profile_id', 'occurred_at'], 'customer_timeline_profile_occurred_idx');
                $table->index('event_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_timeline_events');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_profile_tag');
        Schema::dropIfExists('customer_tags');
        Schema::dropIfExists('customer_metrics');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('customer_code_sequences');
    }
};
