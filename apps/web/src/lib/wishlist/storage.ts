/**
 * Guest wishlist persistence (localStorage).
 * Authenticated users sync via WishlistProvider + backend API.
 */

export const WISHLIST_KEY = "china-order-tz-wishlist";

export type WishlistItem = {
  productId: number;
  /** UUID from Customer API — required for server sync. */
  catalogProductId?: string;
  slug: string;
  name: string;
  imageUrl?: string;
  emoji?: string;
  gradient?: string;
  price?: number;
  addedAt: string;
};

function getBrowserWindow(): (Window & typeof globalThis) | null {
  if (typeof globalThis === "undefined") {
    return null;
  }

  const browserWindow = globalThis.window;
  if (!browserWindow?.localStorage) {
    return null;
  }

  return browserWindow;
}

function readRaw(): WishlistItem[] {
  const browserWindow = getBrowserWindow();
  if (!browserWindow) return [];
  try {
    const raw = browserWindow.localStorage.getItem(WISHLIST_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as WishlistItem[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function writeRaw(items: WishlistItem[]): void {
  const browserWindow = getBrowserWindow();
  if (!browserWindow) return;
  browserWindow.localStorage.setItem(WISHLIST_KEY, JSON.stringify(items));
  browserWindow.dispatchEvent(new Event("wishlist-updated"));
}

export function getWishlistItems(): WishlistItem[] {
  return readRaw();
}

export function clearWishlistStorage(): void {
  const browserWindow = getBrowserWindow();
  if (!browserWindow) return;
  browserWindow.localStorage.removeItem(WISHLIST_KEY);
  browserWindow.dispatchEvent(new Event("wishlist-updated"));
}

export function wishlistItemsMatch(
  a: Pick<WishlistItem, "productId" | "catalogProductId">,
  b: Pick<WishlistItem, "productId" | "catalogProductId">,
): boolean {
  const aCatalog = a.catalogProductId?.trim();
  const bCatalog = b.catalogProductId?.trim();
  if (aCatalog && bCatalog && aCatalog === bCatalog) {
    return true;
  }
  return a.productId === b.productId;
}

export function isInWishlist(
  productId: number,
  catalogProductId?: string,
): boolean {
  return readRaw().some((item) =>
    wishlistItemsMatch(item, { productId, catalogProductId }),
  );
}

export function toggleWishlistItem(item: Omit<WishlistItem, "addedAt">): boolean {
  const current = readRaw();
  const exists = current.some((entry) => wishlistItemsMatch(entry, item));
  if (exists) {
    writeRaw(current.filter((entry) => !wishlistItemsMatch(entry, item)));
    return false;
  }
  writeRaw([{ ...item, addedAt: new Date().toISOString() }, ...current]);
  return true;
}

export function removeWishlistItem(
  productId: number,
  catalogProductId?: string,
): void {
  writeRaw(
    readRaw().filter((entry) => !wishlistItemsMatch(entry, { productId, catalogProductId })),
  );
}
