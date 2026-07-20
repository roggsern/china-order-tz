/**
 * Frontend-only wishlist persistence (localStorage).
 * No backend / API involvement.
 */

const WISHLIST_KEY = "china-order-tz-wishlist";

export type WishlistItem = {
  productId: number;
  slug: string;
  name: string;
  imageUrl?: string;
  emoji?: string;
  gradient?: string;
  price?: number;
  addedAt: string;
};

function readRaw(): WishlistItem[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = localStorage.getItem(WISHLIST_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as WishlistItem[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function writeRaw(items: WishlistItem[]): void {
  if (typeof window === "undefined") return;
  localStorage.setItem(WISHLIST_KEY, JSON.stringify(items));
  window.dispatchEvent(new Event("wishlist-updated"));
}

export function getWishlistItems(): WishlistItem[] {
  return readRaw();
}

export function isInWishlist(productId: number): boolean {
  return readRaw().some((item) => item.productId === productId);
}

export function toggleWishlistItem(item: Omit<WishlistItem, "addedAt">): boolean {
  const current = readRaw();
  const exists = current.some((entry) => entry.productId === item.productId);
  if (exists) {
    writeRaw(current.filter((entry) => entry.productId !== item.productId));
    return false;
  }
  writeRaw([{ ...item, addedAt: new Date().toISOString() }, ...current]);
  return true;
}

export function removeWishlistItem(productId: number): void {
  writeRaw(readRaw().filter((entry) => entry.productId !== productId));
}
