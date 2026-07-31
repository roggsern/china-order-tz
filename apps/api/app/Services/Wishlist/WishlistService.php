<?php

namespace App\Services\Wishlist;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\Features\FeatureAvailabilityService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WishlistService
{
    public function __construct(
        private readonly FeatureAvailabilityService $features,
    ) {}

    /**
     * @return Collection<int, WishlistItem>
     */
    public function listFor(User $user): Collection
    {
        $this->features->assertWishlist();

        return $this->defaultWishlist($user)
            ->items()
            ->with(['product:id,slug,name', 'variant:id,sku'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function add(User $user, string $productId, ?string $variantId = null): WishlistItem
    {
        $this->features->assertWishlist();

        $product = Product::query()->find($productId);
        if ($product === null || ! $product->isPurchasable()) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found.'],
            ]);
        }

        $wishlist = $this->defaultWishlist($user);

        $item = WishlistItem::query()->updateOrCreate(
            [
                'wishlist_id' => $wishlist->id,
                'product_id' => $product->id,
            ],
            [
                'product_variant_id' => $variantId,
            ],
        );

        return $item->fresh(['product', 'variant']);
    }

    public function remove(User $user, string $productId): void
    {
        $this->features->assertWishlist();

        WishlistItem::query()
            ->where('product_id', $productId)
            ->whereHas('wishlist', fn ($q) => $q->where('user_id', $user->id))
            ->delete();
    }

    private function defaultWishlist(User $user): Wishlist
    {
        return Wishlist::query()->firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Default'],
            ['name' => 'Default'],
        );
    }
}
