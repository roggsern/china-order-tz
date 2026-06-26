import "server-only";

import type { Product } from "@/lib/types/catalog";
import { SEED_PRODUCTS } from "@/lib/catalog/seed-products";
import { readServerProducts } from "@/lib/services/product-storage-server";

/**
 * Server-side ProductService — swap this implementation when connecting to the Laravel API.
 */
export async function listServerProducts(): Promise<Product[]> {
  try {
    const stored = await readServerProducts();
    if (stored !== null) {
      return stored;
    }
  } catch {
    // Fall back to seed catalog when server storage is unavailable.
  }

  return SEED_PRODUCTS;
}

export async function getActiveServerProducts(): Promise<Product[]> {
  const products = await listServerProducts();
  return products.filter((product) => product.status === "active");
}
