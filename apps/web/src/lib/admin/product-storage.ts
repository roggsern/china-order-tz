import type { Product } from "@/lib/types/catalog";

const PRODUCTS_STORAGE_KEY = "china-order-tz-admin-products";
const PRODUCTS_INITIALIZED_KEY = "china-order-tz-admin-products-initialized";
export const PRODUCTS_UPDATED_EVENT = "china-order-tz-products-updated";

function readRawProducts(): Product[] {
  if (typeof window === "undefined") {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(PRODUCTS_STORAGE_KEY);
    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw) as Product[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function writeRawProducts(products: Product[]): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(PRODUCTS_STORAGE_KEY, JSON.stringify(products));
  window.dispatchEvent(new CustomEvent(PRODUCTS_UPDATED_EVENT));
}

export function isProductsStorageInitialized(): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  return window.localStorage.getItem(PRODUCTS_INITIALIZED_KEY) === "true";
}

export function markProductsStorageInitialized(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(PRODUCTS_INITIALIZED_KEY, "true");
}

export function getStoredProducts(): Product[] {
  return readRawProducts();
}

export function saveStoredProducts(products: Product[]): void {
  writeRawProducts(products);
}

export function initializeStoredProducts(seedProducts: Product[]): Product[] {
  if (isProductsStorageInitialized()) {
    return getStoredProducts();
  }

  saveStoredProducts(seedProducts);
  markProductsStorageInitialized();
  return seedProducts;
}
