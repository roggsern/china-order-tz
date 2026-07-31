<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping duration SSoT: extend shipping_rates with min/max windows.
 * Prepare order_items duration snapshots for a later checkout wiring phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_rates')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                if (! Schema::hasColumn('shipping_rates', 'estimated_min_days')) {
                    $table->unsignedInteger('estimated_min_days')->nullable()->after('estimated_delivery_days');
                }
                if (! Schema::hasColumn('shipping_rates', 'estimated_max_days')) {
                    $table->unsignedInteger('estimated_max_days')->nullable()->after('estimated_min_days');
                }
            });

            $this->backfillRateWindows();
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'estimated_min_days_snapshot')) {
                    $table->unsignedInteger('estimated_min_days_snapshot')->nullable()->after('estimated_delivery_days');
                }
                if (! Schema::hasColumn('order_items', 'estimated_max_days_snapshot')) {
                    $table->unsignedInteger('estimated_max_days_snapshot')->nullable()->after('estimated_min_days_snapshot');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'estimated_max_days_snapshot')) {
                    $table->dropColumn('estimated_max_days_snapshot');
                }
                if (Schema::hasColumn('order_items', 'estimated_min_days_snapshot')) {
                    $table->dropColumn('estimated_min_days_snapshot');
                }
            });
        }

        if (Schema::hasTable('shipping_rates')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                if (Schema::hasColumn('shipping_rates', 'estimated_max_days')) {
                    $table->dropColumn('estimated_max_days');
                }
                if (Schema::hasColumn('shipping_rates', 'estimated_min_days')) {
                    $table->dropColumn('estimated_min_days');
                }
            });
        }
    }

    private function backfillRateWindows(): void
    {
        $windows = [
            'air_freight' => [7, 12, 10],
            'sea_freight' => [35, 45, 40],
            'local_delivery' => [1, 5, 2],
        ];

        foreach ($windows as $code => [$min, $max, $typical]) {
            $methodId = DB::table('shipping_methods')->where('code', $code)->value('id');
            if ($methodId === null) {
                continue;
            }

            DB::table('shipping_rates')
                ->where('shipping_method_id', $methodId)
                ->whereNull('deleted_at')
                ->update([
                    'estimated_min_days' => $min,
                    'estimated_max_days' => $max,
                    'estimated_delivery_days' => $typical,
                    'updated_at' => now(),
                ]);
        }

        // Any remaining rates: collapse single day into a window.
        DB::table('shipping_rates')
            ->whereNull('deleted_at')
            ->whereNull('estimated_min_days')
            ->whereNotNull('estimated_delivery_days')
            ->update([
                'estimated_min_days' => DB::raw('estimated_delivery_days'),
                'estimated_max_days' => DB::raw('estimated_delivery_days'),
                'updated_at' => now(),
            ]);
    }
};
