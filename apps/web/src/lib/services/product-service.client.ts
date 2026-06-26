import type { Product } from "@/lib/types/catalog";
import { SEED_PRODUCTS } from "@/lib/catalog/seed-products";
import {
  getStoredProducts,
  initializeStoredProducts,
  isProductsStorageInitialized,
  saveStoredProducts,
} from "@/lib/admin/product-storage";

let catalogCache: Product[] | null = null;
let catalogPromise: Promise<Product[]> | null = null;

export function invalidateProductListCache(): void {
  catalogCache = null;
  catalogPromise = null;
}

async function fetchProductCatalog(): Promise<Product[]> {
  if (isProductsStorageInitialized()) {
    return getStoredProducts();
  }

  try {
    const response = await fetch("/api/products", { cache: "force-cache" });
    if (response.ok) {
      const products = (await response.json()) as Product[];
      if (Array.isArray(products)) {
        return products;
      }
    }
  } catch {
    // Fall back to seed catalog when the API is unavailable.
  }

  return SEED_PRODUCTS;
}

/**
 * Client-side ProductService — swap persistence here when connecting to the Laravel API.
 */
export const productService = {
  async list(options?: { refresh?: boolean }): Promise<Product[]> {
    if (options?.refresh) {
      invalidateProductListCache();
    }

    if (catalogCache) {
      return catalogCache;
    }

    if (catalogPromise) {
      return catalogPromise;
    }

    catalogPromise = fetchProductCatalog()
      .then((products) => {
        catalogCache = products;
        catalogPromise = null;
        return products;
      })
      .catch((error) => {
        catalogPromise = null;
        throw error;
      });

    return catalogPromise;
  },

  initialize(seedProducts: Product[]): Product[] {
    invalidateProductListCache();
    return initializeStoredProducts(seedProducts);
  },

  saveAll(products: Product[]): void {
    saveStoredProducts(products);
    invalidateProductListCache();
    catalogCache = products;
    void syncProductsToServer(products);
  },

  async syncToServer(products: Product[]): Promise<void> {
    await syncProductsToServer(products);
  },
};

async function syncProductsToServer(products: Product[]): Promise<void> {
  try {
    await fetch("/api/products", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ products }),
    });
  } catch {
    // Ignore network failures; localStorage remains the admin source on the client.
  }
}
