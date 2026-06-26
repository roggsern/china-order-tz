import "server-only";

import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import type { Product } from "@/lib/types/catalog";

const DATA_DIR = path.join(process.cwd(), ".data");
const STORE_PATH = path.join(DATA_DIR, "products-store.json");

type ProductStore = {
  initialized: boolean;
  products: Product[];
};

async function ensureDataDir(): Promise<void> {
  await mkdir(DATA_DIR, { recursive: true });
}

async function readStore(): Promise<ProductStore | null> {
  try {
    const raw = await readFile(STORE_PATH, "utf8");
    const parsed = JSON.parse(raw) as ProductStore;
    if (!parsed || typeof parsed !== "object" || !Array.isArray(parsed.products)) {
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}

export async function readServerProducts(): Promise<Product[] | null> {
  const store = await readStore();
  if (!store?.initialized) {
    return null;
  }
  return store.products;
}

export async function writeServerProducts(products: Product[]): Promise<void> {
  await ensureDataDir();
  const payload: ProductStore = {
    initialized: true,
    products,
  };
  await writeFile(STORE_PATH, JSON.stringify(payload, null, 2), "utf8");
}

export async function isServerProductsInitialized(): Promise<boolean> {
  const store = await readStore();
  return store?.initialized === true;
}
