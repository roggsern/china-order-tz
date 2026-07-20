<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Commerce Mode Engine — channels for Buy From China / Buy From Tanzania.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_channels')) {
            Schema::create('commerce_channels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $chinaId = $this->ensureChannel(
            'CHINA_IMPORT',
            'Buy From China',
            'Import commerce channel — air/sea shipping and customer agent delivery.',
        );
        $tzId = $this->ensureChannel(
            'TZ_LOCAL',
            'Buy From Tanzania',
            'Local Tanzania commerce channel — self pickup and negotiated delivery.',
        );

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'commerce_channel_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignUuid('commerce_channel_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('commerce_channels')
                    ->nullOnDelete();
                $table->index('commerce_channel_id');
            });

            // Backfill from legacy fulfillment_source.
            if (Schema::hasColumn('products', 'fulfillment_source')) {
                DB::table('products')
                    ->where('fulfillment_source', 'buy_from_tz')
                    ->whereNull('commerce_channel_id')
                    ->update(['commerce_channel_id' => $tzId]);

                DB::table('products')
                    ->where(function ($q) {
                        $q->whereNull('fulfillment_source')
                            ->orWhere('fulfillment_source', '!=', 'buy_from_tz');
                    })
                    ->whereNull('commerce_channel_id')
                    ->update(['commerce_channel_id' => $chinaId]);
            } else {
                DB::table('products')
                    ->whereNull('commerce_channel_id')
                    ->update(['commerce_channel_id' => $chinaId]);
            }
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'commerce_channel_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignUuid('commerce_channel_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('commerce_channels')
                    ->nullOnDelete();
                $table->json('commerce_channel_snapshot')->nullable()->after('commerce_channel_id');
                $table->index('commerce_channel_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'commerce_channel_id')) {
                    $table->dropConstrainedForeignId('commerce_channel_id');
                }
                if (Schema::hasColumn('orders', 'commerce_channel_snapshot')) {
                    $table->dropColumn('commerce_channel_snapshot');
                }
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'commerce_channel_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('commerce_channel_id');
            });
        }

        Schema::dropIfExists('commerce_channels');
    }

    private function ensureChannel(string $code, string $name, string $description): string
    {
        $existing = DB::table('commerce_channels')->where('code', $code)->first();
        if ($existing !== null) {
            return (string) $existing->id;
        }

        $id = (string) Str::uuid();
        DB::table('commerce_channels')->insert([
            'id' => $id,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
};
