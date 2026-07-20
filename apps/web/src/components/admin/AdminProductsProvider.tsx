"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import type { Product, ProductFormData } from "@/lib/types/catalog";
import {
  AdminCatalogApiError,
  createAdminProduct,
  deleteAdminProduct,
  fetchAdminProducts,
  persistProductImages,
  setAdminProductImagePrimary,
  updateAdminProduct,
} from "@/lib/api/admin-catalog";
import { productToFormData } from "@/lib/admin/product-utils";
import { useAdminAuth } from "@/components/admin/AdminAuthProvider";

type AdminProductsContextValue = {
  products: Product[];
  isHydrated: boolean;
  isLoading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  addProduct: (
    data: ProductFormData,
    options?: { pendingFiles?: Map<number, File> },
  ) => Promise<Product>;
  updateProduct: (
    id: number,
    data: ProductFormData,
    options?: { pendingFiles?: Map<number, File> },
  ) => Promise<Product>;
  deleteProduct: (id: number) => Promise<void>;
  deleteProducts: (ids: number[]) => Promise<void>;
  getProduct: (id: number) => Product | undefined;
};

const AdminProductsContext = createContext<AdminProductsContextValue | null>(null);

export function AdminProductsProvider({ children }: { children: ReactNode }) {
  const { isAuthenticated, isReady } = useAdminAuth();
  const [products, setProducts] = useState<Product[]>([]);
  const [isHydrated, setIsHydrated] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadProducts = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const { products: nextProducts } = await fetchAdminProducts();
      setProducts(nextProducts);
      setIsHydrated(true);
    } catch (err) {
      const message =
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load products from the API.";
      setError(message);
      setProducts([]);
      setIsHydrated(true);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isReady) {
      return;
    }

    if (!isAuthenticated) {
      setProducts([]);
      setError(null);
      setIsHydrated(false);
      setIsLoading(false);
      return;
    }

    void loadProducts();
  }, [isAuthenticated, isReady, loadProducts]);

  const addProduct = useCallback(
    async (data: ProductFormData, options?: { pendingFiles?: Map<number, File> }) => {
      const created = await createAdminProduct(data);
      const catalogProductId = created.catalogProductId;

      if (catalogProductId && options?.pendingFiles?.size) {
        const images = await persistProductImages({
          catalogProductId,
          images: data.images,
          thumbnailImageId: data.thumbnailImageId,
          pendingFiles: options.pendingFiles,
        });
        created.images = images;
        created.primary_image = images[0];
        created.image = images[0]?.url ?? images[0]?.path;
      }

      setProducts((prev) => [created, ...prev]);
      setError(null);
      return created;
    },
    [],
  );

  const updateProduct = useCallback(
    async (
      id: number,
      data: ProductFormData,
      options?: { pendingFiles?: Map<number, File> },
    ) => {
      const existing = products.find((product) => product.id === id);
      const catalogProductId = existing?.catalogProductId;

      if (!catalogProductId) {
        throw new AdminCatalogApiError("Product is missing a backend id.", 422);
      }

      let updated = await updateAdminProduct(catalogProductId, data);

      if (options?.pendingFiles?.size) {
        const images = await persistProductImages({
          catalogProductId,
          images: data.images,
          thumbnailImageId: data.thumbnailImageId,
          pendingFiles: options.pendingFiles,
        });
        updated = {
          ...updated,
          images,
          primary_image: images[0],
          image: images[0]?.url ?? images[0]?.path,
          thumbnailImageId: images[0]?.id,
        };
      } else {
        const preferred =
          data.images.find((image) => image.id === data.thumbnailImageId) ?? data.images[0];
        if (preferred?.catalogImageId) {
          await setAdminProductImagePrimary(preferred.catalogImageId);
        }
        if (data.images.length) {
          updated = {
            ...updated,
            images: data.images,
            primary_image: preferred ?? data.images[0],
            image: preferred?.url ?? preferred?.path ?? data.images[0]?.url,
            thumbnailImageId: preferred?.id ?? data.images[0]?.id,
          };
        }
      }

      setProducts((prev) =>
        prev.map((product) => (product.id === id ? { ...updated, id: product.id } : product)),
      );
      setError(null);
      return updated;
    },
    [products],
  );

  const deleteProduct = useCallback(
    async (id: number) => {
      const target = products.find((product) => product.id === id);
      const catalogId = target?.catalogProductId;

      if (!catalogId) {
        throw new AdminCatalogApiError("Product is missing a backend id.", 422);
      }

      await deleteAdminProduct(catalogId);
      setProducts((prev) => prev.filter((product) => product.id !== id));
      setError(null);
    },
    [products],
  );

  const deleteProducts = useCallback(
    async (ids: number[]) => {
      if (!ids.length) return;

      const idSet = new Set(ids);
      const targets = products.filter((product) => idSet.has(product.id));
      const catalogIds = targets
        .map((product) => product.catalogProductId)
        .filter((catalogId): catalogId is string => Boolean(catalogId));

      await Promise.all(catalogIds.map((catalogId) => deleteAdminProduct(catalogId)));
      setProducts((prev) => prev.filter((product) => !idSet.has(product.id)));
      setError(null);
    },
    [products],
  );

  const getProduct = useCallback(
    (id: number) => products.find((product) => product.id === id),
    [products],
  );

  return (
    <AdminProductsContext.Provider
      value={{
        products,
        isHydrated: isAuthenticated ? isHydrated && !isLoading : false,
        isLoading,
        error,
        refetch: loadProducts,
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
