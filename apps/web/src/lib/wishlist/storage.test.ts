import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  clearWishlistStorage,
  getWishlistItems,
  isInWishlist,
  removeWishlistItem,
  toggleWishlistItem,
  WISHLIST_KEY,
  wishlistItemsMatch,
} from "./storage";

function withMockWindow(run: () => void) {
  const storage = new Map<string, string>();
  const originalWindow = globalThis.window;

  const mockWindow = {
    localStorage: {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => {
        storage.set(key, value);
      },
      removeItem: (key: string) => {
        storage.delete(key);
      },
    },
    dispatchEvent: () => true,
  } as unknown as Window & typeof globalThis;

  Object.defineProperty(globalThis, "window", {
    configurable: true,
    writable: true,
    value: mockWindow,
  });

  try {
    run();
  } finally {
    if (originalWindow === undefined) {
      delete (globalThis as { window?: Window }).window;
    } else {
      Object.defineProperty(globalThis, "window", {
        configurable: true,
        writable: true,
        value: originalWindow,
      });
    }
  }
}

describe("wishlist storage", () => {
  it("stores guest wishlist items in localStorage", () => {
    withMockWindow(() => {
      const added = toggleWishlistItem({
        productId: 42,
        catalogProductId: "019f7a6e-4d46-7376-aca4-aed79f33519b",
        slug: "guest-product",
        name: "Guest Product",
      });

      assert.equal(added, true);
      assert.equal(getWishlistItems().length, 1);
      assert.equal(getWishlistItems()[0]?.slug, "guest-product");
    });
  });

  it("prevents duplicate guest items by product id or catalog id", () => {
    withMockWindow(() => {
      toggleWishlistItem({
        productId: 42,
        catalogProductId: "019f7a6e-4d46-7376-aca4-aed79f33519b",
        slug: "guest-product",
        name: "Guest Product",
      });

      const addedAgain = toggleWishlistItem({
        productId: 42,
        catalogProductId: "019f7a6e-4d46-7376-aca4-aed79f33519b",
        slug: "guest-product",
        name: "Guest Product",
      });

      assert.equal(addedAgain, false);
      assert.equal(getWishlistItems().length, 0);
    });
  });

  it("matches items by catalog product id when numeric ids differ", () => {
    assert.equal(
      wishlistItemsMatch(
        { productId: 1, catalogProductId: "prod-a" },
        { productId: 2, catalogProductId: "prod-a" },
      ),
      true,
    );
  });

  it("clears guest storage after merge workflow", () => {
    withMockWindow(() => {
      toggleWishlistItem({
        productId: 7,
        catalogProductId: "prod-clear",
        slug: "clear-me",
        name: "Clear Me",
      });

      assert.equal(isInWishlist(7, "prod-clear"), true);
      clearWishlistStorage();
      assert.equal(getWishlistItems().length, 0);
      assert.equal(isInWishlist(7, "prod-clear"), false);
    });
  });

  it("removes a specific wishlist item", () => {
    withMockWindow(() => {
      toggleWishlistItem({
        productId: 1,
        catalogProductId: "prod-1",
        slug: "one",
        name: "One",
      });
      toggleWishlistItem({
        productId: 2,
        catalogProductId: "prod-2",
        slug: "two",
        name: "Two",
      });

      removeWishlistItem(1, "prod-1");

      assert.equal(getWishlistItems().length, 1);
      assert.equal(getWishlistItems()[0]?.slug, "two");
    });
  });

  it("uses stable storage key", () => {
    assert.equal(WISHLIST_KEY, "china-order-tz-wishlist");
  });
});
