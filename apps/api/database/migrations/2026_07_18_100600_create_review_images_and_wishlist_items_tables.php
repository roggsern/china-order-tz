<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * MASTER_SPECIFICATION: review_images; wishlists (container) + wishlist_items;
 * additive review body/status columns.
 *
 * Legacy `wishlists` stored line items directly. Reshape to container + items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['review_id', 'sort_order']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->text('body')->nullable()->after('title');
            $table->string('status')->nullable()->after('is_approved')->index();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wishlist_id');
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['wishlist_id', 'product_id', 'product_variant_id'], 'wishlist_items_unique');
            $table->index(['wishlist_id', 'created_at']);
        });

        $this->reshapeWishlistsToContainers();

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->foreign('wishlist_id')->references('id')->on('wishlists')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropForeign(['wishlist_id']);
        });

        $this->restoreWishlistsAsLineItems();

        Schema::dropIfExists('wishlist_items');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['body', 'status']);
        });

        Schema::dropIfExists('review_images');
    }

    private function reshapeWishlistsToContainers(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
        });

        $legacyRows = DB::table('wishlists')
            ->whereNotNull('product_id')
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->get();

        $containerByUser = [];
        $now = now();

        foreach ($legacyRows as $row) {
            if (! isset($containerByUser[$row->user_id])) {
                $containerByUser[$row->user_id] = $row->id;
                DB::table('wishlists')->where('id', $row->id)->update([
                    'name' => 'Default',
                    'updated_at' => $now,
                ]);
            }

            $wishlistId = $containerByUser[$row->user_id];

            DB::table('wishlist_items')->insert([
                'id' => (string) Str::uuid(),
                'wishlist_id' => $wishlistId,
                'product_id' => $row->product_id,
                'product_variant_id' => $row->product_variant_id,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
                'deleted_at' => $row->deleted_at,
            ]);

            if ($row->id !== $wishlistId) {
                DB::table('wishlists')->where('id', $row->id)->delete();
            }
        }

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'product_id', 'product_variant_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_id', 'product_variant_id']);
        });
    }

    private function restoreWishlistsAsLineItems(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->uuid('product_id')->nullable()->after('name');
            $table->uuid('product_variant_id')->nullable()->after('product_id');
        });

        $items = DB::table('wishlist_items')->orderBy('created_at')->get();
        $now = now();

        foreach ($items as $item) {
            $wishlist = DB::table('wishlists')->where('id', $item->wishlist_id)->first();
            if ($wishlist === null) {
                continue;
            }

            if ($wishlist->product_id === null) {
                DB::table('wishlists')->where('id', $wishlist->id)->update([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('wishlists')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $wishlist->user_id,
                'name' => $wishlist->name,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'created_at' => $item->created_at ?? $now,
                'updated_at' => $item->updated_at ?? $now,
                'deleted_at' => $item->deleted_at,
            ]);
        }

        Schema::table('wishlists', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->unique(['user_id', 'product_id', 'product_variant_id']);
            $table->dropColumn('name');
        });
    }
};
