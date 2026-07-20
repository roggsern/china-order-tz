import type { Product, ProductImage } from "@/lib/types/catalog";

const STORAGE_KEY = "china-order-tz-recently-viewed";
const MAX_ITEMS = 12;

export type RecentlyViewedProduct = {
  id: number;
  slug: string;
  name: string;
  price: number;
  oldPrice: number;
  rating: number;
  emoji: string;
  gradient: string;
  image?: ProductImage;
};

export function toRecentlyViewedProduct(product: Product): RecentlyViewedProduct {
  return {
    id: product.id,
    slug: product.slug,
    name: product.name,
    price: product.price,
    oldPrice: product.oldPrice,
    rating: product.rating,
    emoji: product.emoji,
    gradient: product.gradient,
    image: product.primary_image ?? product.images[0],
  };
}

export function getRecentlyViewed(): RecentlyViewedProduct[] {
  if (typeof window === "undefined") return [];

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as RecentlyViewedProduct[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export function trackRecentlyViewed(product: Product): void {
  if (typeof window === "undefined") return;

  const entry = toRecentlyViewedProduct(product);
  const existing = getRecentlyViewed().filter((item) => item.id !== product.id);
  const updated = [entry, ...existing].slice(0, MAX_ITEMS);

  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
  } catch {
    // Ignore quota errors
  }
}
