import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { ServerWishlistItem } from "@/lib/api/customer-wishlist";
import type { WishlistItem } from "@/lib/wishlist/storage";
import {
  buildWishlistMetadataMap,
  filterLocalItemsForWishlistSync,
  isLocalWishlistItemAlreadyOnServer,
} from "./sync-local-to-server";

const localItem: WishlistItem = {
  productId: 101,
  catalogProductId: "prod-1",
  slug: "local-slug",
  name: "Local Item",
  addedAt: "2026-01-01T00:00:00.000Z",
};

describe("wishlist login merge", () => {
  it("syncs all local items when server wishlist is empty", () => {
    assert.deepEqual(filterLocalItemsForWishlistSync([localItem], []), [localItem]);
  });

  it("skips items without catalog product id during sync", () => {
    const filtered = filterLocalItemsForWishlistSync(
      [{ ...localItem, catalogProductId: undefined }],
      [],
    );

    assert.equal(filtered.length, 0);
  });

  it("prevents duplicate merge when product already exists on server", () => {
    const serverItems: ServerWishlistItem[] = [
      {
        id: "wish-1",
        product_id: "prod-1",
        product: { slug: "server-slug", name: "Server Item" },
      },
    ];

    assert.equal(isLocalWishlistItemAlreadyOnServer(localItem, serverItems), true);
    assert.equal(filterLocalItemsForWishlistSync([localItem], serverItems).length, 0);
  });

  it("preserves local metadata for merged server items", () => {
    const metadata = buildWishlistMetadataMap([
      {
        ...localItem,
        emoji: "📱",
        gradient: "from-zinc-100 to-zinc-200",
        price: 25000,
      },
    ]);

    assert.equal(metadata.get("prod-1")?.emoji, "📱");
    assert.equal(metadata.get("prod-1")?.price, 25000);
    assert.equal(metadata.get("prod-1")?.slug, "local-slug");
  });
});
