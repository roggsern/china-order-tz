import type { ServerWishlistItem } from "@/lib/api/customer-wishlist";
import type { WishlistItem } from "@/lib/wishlist/storage";

/** Avoid duplicating items when guest wishlist is merged into an account wishlist. */
export function isLocalWishlistItemAlreadyOnServer(
  localItem: WishlistItem,
  serverItems: ServerWishlistItem[],
): boolean {
  const localProductId = localItem.catalogProductId?.trim();
  if (!localProductId) {
    return false;
  }

  return serverItems.some(
    (serverItem) => serverItem.product_id?.trim() === localProductId,
  );
}

export function filterLocalItemsForWishlistSync(
  localItems: WishlistItem[],
  serverItems: ServerWishlistItem[],
): WishlistItem[] {
  const syncable = localItems.filter((item) => item.catalogProductId?.trim());

  if (serverItems.length === 0) {
    return syncable;
  }

  return syncable.filter(
    (item) => !isLocalWishlistItemAlreadyOnServer(item, serverItems),
  );
}

export function buildWishlistMetadataMap(
  localItems: WishlistItem[],
): Map<string, Partial<WishlistItem>> {
  const map = new Map<string, Partial<WishlistItem>>();

  for (const item of localItems) {
    const catalogProductId = item.catalogProductId?.trim();
    if (!catalogProductId) {
      continue;
    }

    map.set(catalogProductId, {
      productId: item.productId,
      slug: item.slug,
      name: item.name,
      imageUrl: item.imageUrl,
      emoji: item.emoji,
      gradient: item.gradient,
      price: item.price,
      addedAt: item.addedAt,
    });
  }

  return map;
}
