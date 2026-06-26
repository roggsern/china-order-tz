"use client";

import { useEffect, useRef, useState } from "react";
import type { Product } from "@/lib/types/catalog";
import { PRODUCTS_UPDATED_EVENT } from "@/lib/admin/product-storage";
import { SEED_PRODUCTS } from "@/lib/catalog/seed-products";
import { productService } from "@/lib/services/product-service.client";

async function loadActiveProducts(refresh = false): Promise<Product[]> {
  const catalog = await productService.list(refresh ? { refresh: true } : undefined);
  return catalog.filter((product) => product.status === "active");
}

export function useCatalogProducts(): Product[] {
  const [products, setProducts] = useState<Product[]>(SEED_PRODUCTS);
  const initializedRef = useRef(false);

  useEffect(() => {
    if (initializedRef.current) {
      return;
    }
    initializedRef.current = true;

    let active = true;

    void loadActiveProducts().then((catalog) => {
      if (active) {
        setProducts(catalog);
      }
    });

    const refresh = () => {
      void loadActiveProducts(true).then((catalog) => {
        if (active) {
          setProducts(catalog);
        }
      });
    };

    const onStorage = (event: StorageEvent) => {
      if (event.key === "china-order-tz-admin-products") {
        refresh();
      }
    };

    const onProductsUpdated = () => refresh();

    window.addEventListener("storage", onStorage);
    window.addEventListener(PRODUCTS_UPDATED_EVENT, onProductsUpdated);
    return () => {
      active = false;
      window.removeEventListener("storage", onStorage);
      window.removeEventListener(PRODUCTS_UPDATED_EVENT, onProductsUpdated);
    };
  }, []);

  return products;
}
