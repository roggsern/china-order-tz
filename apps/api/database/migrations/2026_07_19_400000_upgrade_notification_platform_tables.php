<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise Notification Platform — templates + extended notification log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('channel');
                $table->string('subject')->nullable();
                $table->text('body');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['channel', 'is_active']);
            });
        }

        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'customer_id')) {
                $table->foreignUuid('customer_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('notifications', 'admin_id')) {
                $table->foreignUuid('admin_id')->nullable()->after('customer_id')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('notifications', 'event_type')) {
                $table->string('event_type')->nullable()->after('admin_id')->index();
            }
            if (! Schema::hasColumn('notifications', 'template_key')) {
                $table->string('template_key')->nullable()->after('event_type')->index();
            }
            if (! Schema::hasColumn('notifications', 'channel')) {
                $table->string('channel')->default('in_app')->after('message')->index();
            }
            if (! Schema::hasColumn('notifications', 'status')) {
                $table->string('status')->default('pending')->after('channel')->index();
            }
            if (! Schema::hasColumn('notifications', 'provider')) {
                $table->string('provider')->nullable()->after('status');
            }
            if (! Schema::hasColumn('notifications', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('notifications', 'error_message')) {
                $table->text('error_message')->nullable()->after('data');
            }
            if (! Schema::hasColumn('notifications', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('error_message');
            }
        });

        // Backfill from legacy columns (UUID PKs — avoid chunkById).
        foreach (DB::table('notifications')->orderBy('created_at')->get() as $row) {
            DB::table('notifications')->where('id', $row->id)->update([
                'customer_id' => $row->customer_id ?? $row->user_id,
                'event_type' => $row->event_type ?? $row->type,
                'channel' => $row->channel ?? 'in_app',
                'status' => $row->read_at
                    ? 'read'
                    : ($row->status ?? 'sent'),
                'sent_at' => $row->sent_at ?? $row->created_at,
                'provider' => $row->provider ?? 'in_app',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasColumn('notifications', 'customer_id')) {
                    $table->dropConstrainedForeignId('customer_id');
                }
                if (Schema::hasColumn('notifications', 'admin_id')) {
                    $table->dropConstrainedForeignId('admin_id');
                }
                foreach ([
                    'event_type',
                    'template_key',
                    'channel',
                    'status',
                    'provider',
                    'provider_message_id',
                    'error_message',
                    'sent_at',
                ] as $column) {
                    if (Schema::hasColumn('notifications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('notification_templates');
    }
};
