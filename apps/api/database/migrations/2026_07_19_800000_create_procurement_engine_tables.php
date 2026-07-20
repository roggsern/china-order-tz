<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Supplier & Procurement Engine — extend suppliers + PO / receiving tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('suppliers', 'code')) {
                    $table->string('code')->nullable()->after('name');
                }
                if (! Schema::hasColumn('suppliers', 'payment_terms')) {
                    $table->string('payment_terms')->nullable()->after('country');
                }
                if (! Schema::hasColumn('suppliers', 'notes')) {
                    $table->text('notes')->nullable()->after('payment_terms');
                }
            });

            // Backfill unique codes from slug/name for existing rows.
            $rows = DB::table('suppliers')->select('id', 'slug', 'name', 'code')->get();
            foreach ($rows as $row) {
                if (filled($row->code ?? null)) {
                    continue;
                }
                $base = Str::upper(Str::slug((string) ($row->slug ?: $row->name), '_'));
                $base = $base !== '' ? $base : 'SUP';
                $code = $base;
                $n = 1;
                while (DB::table('suppliers')->where('code', $code)->where('id', '!=', $row->id)->exists()) {
                    $code = $base.'_'.$n;
                    $n++;
                }
                DB::table('suppliers')->where('id', $row->id)->update(['code' => $code]);
            }

            try {
                Schema::table('suppliers', function (Blueprint $table) {
                    $table->unique('code');
                });
            } catch (\Throwable) {
                // Index may already exist.
            }
        }

        if (! Schema::hasTable('supplier_products')) {
            Schema::create('supplier_products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->string('supplier_sku')->nullable();
                $table->decimal('purchase_cost', 14, 2);
                $table->string('currency', 3)->default('TZS');
                $table->unsignedInteger('lead_time_days')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['supplier_id', 'product_variant_id']);
                $table->index(['product_variant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->string('purchase_number')->unique();
                $table->string('status')->default('draft');
                $table->string('currency', 3)->default('TZS');
                $table->text('notes')->nullable();
                $table->timestamp('ordered_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['supplier_id', 'status']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignUuid('product_variant_id')->constrained('product_variants')->restrictOnDelete();
                $table->unsignedInteger('quantity_ordered');
                $table->unsignedInteger('quantity_received')->default(0);
                $table->decimal('unit_cost', 14, 2);
                $table->string('currency', 3)->default('TZS');
                $table->timestamps();

                $table->index(['purchase_order_id', 'product_variant_id']);
            });
        }

        if (! Schema::hasTable('receiving_records')) {
            Schema::create('receiving_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignUuid('received_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->timestamp('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['purchase_order_id', 'status']);
            });
        }

        if (! Schema::hasTable('receiving_record_items')) {
            Schema::create('receiving_record_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('receiving_record_id')->constrained('receiving_records')->cascadeOnDelete();
                $table->foreignUuid('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
                $table->unsignedInteger('quantity_received');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_cost_histories')) {
            Schema::create('supplier_cost_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->foreignUuid('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
                $table->decimal('purchase_cost', 14, 2);
                $table->string('currency', 3)->default('TZS');
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(
                    ['product_variant_id', 'supplier_id', 'recorded_at'],
                    'supplier_cost_hist_variant_supplier_recorded_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_cost_histories');
        Schema::dropIfExists('receiving_record_items');
        Schema::dropIfExists('receiving_records');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_products');

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (Schema::hasColumn('suppliers', 'code')) {
                    try {
                        $table->dropUnique(['code']);
                    } catch (\Throwable) {
                    }
                    $table->dropColumn('code');
                }
                if (Schema::hasColumn('suppliers', 'payment_terms')) {
                    $table->dropColumn('payment_terms');
                }
                if (Schema::hasColumn('suppliers', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
