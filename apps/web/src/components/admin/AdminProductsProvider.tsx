"use client";

import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from "react";
import type { Product, ProductFormData } from "@/lib/types/catalog";
import { productService } from "@/lib/services/product-service.client";
import {
  enrichProductForAdmin,
  formDataToProduct,
  productToFormData,
} from "@/lib/admin/product-utils";
import {
  getStoredProducts,
  isProductsStorageInitialized,
  markProductsStorageInitialized,
  saveStoredProducts,
} from "@/lib/admin/product-storage";
import { SEED_PRODUCTS } from "@/lib/catalog/seed-products";

type AdminProductsContextValue = {
  products: Product[];
  isHydrated: boolean;
  addProduct: (data: ProductFormData) => void;
  updateProduct: (id: number, data: ProductFormData) => void;
  deleteProduct: (id: number) => void;
  deleteProducts: (ids: number[]) => void;
  getProduct: (id: number) => Product | undefined;
};

const AdminProductsContext = createContext<AdminProductsContextValue | null>(null);

const enrichedSeedProducts = SEED_PRODUCTS.map(enrichProductForAdmin);

function persistProducts(products: Product[]) {
  productService.saveAll(products);
}

export function AdminProductsProvider({ children }: { children: ReactNode }) {
  const [products, setProducts] = useState<Product[]>(enrichedSeedProducts);
  const [isHydrated, setIsHydrated] = useState(false);
  const initializedRef = useRef(false);

  useEffect(() => {
    if (initializedRef.current) {
      return;
    }
    initializedRef.current = true;

    let cancelled = false;

    async function hydrateProducts() {
      if (isProductsStorageInitialized()) {
        const stored = getStoredProducts().map(enrichProductForAdmin);
        if (!cancelled) {
          setProducts(stored);
          setIsHydrated(true);
        }
        return;
      }

      try {
        const response = await fetch("/api/products", { cache: "no-store" });
        if (response.ok) {
          const serverProducts = (await response.json()) as Product[];
          if (Array.isArray(serverProducts) && serverProducts.length > 0) {
            saveStoredProducts(serverProducts);
            markProductsStorageInitialized();
            if (!cancelled) {
              setProducts(serverProducts.map(enrichProductForAdmin));
              setIsHydrated(true);
            }
            return;
          }
        }
      } catch {
        // Fall back to seed catalog below.
      }

      const seeded = productService.initialize(enrichedSeedProducts);
      const nextProducts = seeded.map(enrichProductForAdmin);
      if (!cancelled) {
        setProducts(nextProducts);
        setIsHydrated(true);
      }
      void productService.syncToServer(seeded);
    }

    void hydrateProducts();

    return () => {
      cancelled = true;
    };
  }, []);

  const addProduct = useCallback((data: ProductFormData) => {
    setProducts((prev) => {
      const nextId = Math.max(0, ...prev.map((product) => product.id)) + 1;
      const next = [...prev, formDataToProduct(data, nextId)];
      persistProducts(next);
      return next;
    });
  }, []);

  const updateProduct = useCallback((id: number, data: ProductFormData) => {
    setProducts((prev) => {
      const existing = prev.find((product) => product.id === id);
      const next = prev.map((product) =>
        product.id === id
          ? formDataToProduct(data, id, existing?.createdAt)
          : product,
      );
      persistProducts(next);
      return next;
    });
  }, []);

  const deleteProduct = useCallback((id: number) => {
    setProducts((prev) => {
      const next = prev.filter((product) => product.id !== id);
      persistProducts(next);
      return next;
    });
  }, []);

  const deleteProducts = useCallback((ids: number[]) => {
    if (!ids.length) return;
    setProducts((prev) => {
      const idSet = new Set(ids);
      const next = prev.filter((product) => !idSet.has(product.id));
      persistProducts(next);
      return next;
    });
  }, []);

  const getProduct = useCallback(
    (id: number) => products.find((product) => product.id === id),
    [products],
  );

  return (
    <AdminProductsContext.Provider
      value={{
        products,
        isHydrated,
        addProduct,
        updateProduct,
        deleteProduct,
        deleteProducts,
        getProduct,
      }}
    >
      {children}
    </AdminProductsContext.Provider>
  );
}

export function useAdminProducts() {
  const ctx = useContext(AdminProductsContext);
  if (!ctx) {
    throw new Error("useAdminProducts must be used within AdminProductsProvider");
  }
  return ctx;
}

export { productToFormData };
