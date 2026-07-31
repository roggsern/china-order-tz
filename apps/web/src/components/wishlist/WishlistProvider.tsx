"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  addServerWishlistItem,
  fetchServerWishlist,
  mapServerWishlistItems,
  removeServerWishlistItem,
} from "@/lib/api/customer-wishlist";
import { showWishlistToast } from "@/lib/customer/customer-toast";
import { WishlistContext, type WishlistContextValue } from "@/lib/wishlist/context";
import {
  buildWishlistMetadataMap,
  filterLocalItemsForWishlistSync,
} from "@/lib/wishlist/sync-local-to-server";
import {
  clearWishlistStorage,
  getWishlistItems,
  removeWishlistItem,
  toggleWishlistItem,
  wishlistItemsMatch,
  type WishlistItem,
} from "@/lib/wishlist/storage";

export function WishlistProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<WishlistItem[]>([]);
  const [ready, setReady] = useState(false);
  const serverModeRef = useRef(false);
  const itemsRef = useRef(items);
  itemsRef.current = items;

  const applyItems = useCallback((nextItems: WishlistItem[], serverMode: boolean) => {
    serverModeRef.current = serverMode;
    setItems(nextItems);
  }, []);

  const syncLocalWishlistToServer = useCallback(
    async (token: string): Promise<WishlistItem[]> => {
      const localItems = getWishlistItems();
      let serverItems = await fetchServerWishlist(token);
      const toSync = filterLocalItemsForWishlistSync(localItems, serverItems);

      for (const item of toSync) {
        const catalogProductId = item.catalogProductId?.trim();
        if (!catalogProductId) {
          continue;
        }

        try {
          await addServerWishlistItem({ productId: catalogProductId }, token);
        } catch {
          // Duplicate or validation errors are non-blocking during merge.
        }
      }

      if (toSync.length > 0) {
        clearWishlistStorage();
      }

      serverItems = await fetchServerWishlist(token);
      const metadata = buildWishlistMetadataMap(localItems);
      return mapServerWishlistItems(serverItems, metadata);
    },
    [],
  );

  const hydrateWishlist = useCallback(async () => {
    const token = getCustomerApiToken();

    if (!token) {
      applyItems(getWishlistItems(), false);
      setReady(true);
      return;
    }

    try {
      const serverItems = await syncLocalWishlistToServer(token);
      applyItems(serverItems, true);
    } catch {
      applyItems(getWishlistItems(), false);
    } finally {
      setReady(true);
    }
  }, [applyItems, syncLocalWishlistToServer]);

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }

    void hydrateWishlist();

    const refreshLocal = () => {
      if (!serverModeRef.current) {
        setItems(getWishlistItems());
      }
    };

    window.addEventListener("wishlist-updated", refreshLocal);
    window.addEventListener("storage", refreshLocal);

    return () => {
      window.removeEventListener("wishlist-updated", refreshLocal);
      window.removeEventListener("storage", refreshLocal);
    };
  }, [hydrateWishlist]);

  useEffect(() => {
    if (!ready || typeof window === "undefined") {
      return;
    }

    const onSessionUpdated = () => {
      void hydrateWishlist();
    };

    window.addEventListener("customer-session-updated", onSessionUpdated);
    return () => {
      window.removeEventListener("customer-session-updated", onSessionUpdated);
    };
  }, [hydrateWishlist, ready]);

  const isSaved = useCallback(
    (productId: number, catalogProductId?: string) =>
      itemsRef.current.some((item) =>
        wishlistItemsMatch(item, { productId, catalogProductId }),
      ),
    [],
  );

  const toggle = useCallback(
    async (item: Omit<WishlistItem, "addedAt">): Promise<boolean> => {
      const token = getCustomerApiToken();
      const catalogProductId = item.catalogProductId?.trim();

      if (token && catalogProductId) {
        const currentlySaved = itemsRef.current.some((entry) =>
          wishlistItemsMatch(entry, item),
        );

        try {
          if (currentlySaved) {
            await removeServerWishlistItem(catalogProductId, token);
            const serverItems = await fetchServerWishlist(token);
            const metadata = buildWishlistMetadataMap(itemsRef.current);
            applyItems(mapServerWishlistItems(serverItems, metadata), true);
            showWishlistToast(false);
            return false;
          }

          await addServerWishlistItem({ productId: catalogProductId }, token);
          const serverItems = await fetchServerWishlist(token);
          const metadata = buildWishlistMetadataMap([
            ...itemsRef.current,
            { ...item, addedAt: new Date().toISOString() },
          ]);
          applyItems(mapServerWishlistItems(serverItems, metadata), true);
          showWishlistToast(true);
          return true;
        } catch {
          // Fall back to local storage when server is unavailable.
        }
      }

      const added = toggleWishlistItem(item);
      showWishlistToast(added);
      setItems(getWishlistItems());
      return added;
    },
    [applyItems],
  );

  const remove = useCallback(
    (productId: number, catalogProductId?: string) => {
      const token = getCustomerApiToken();
      const catalogId = catalogProductId?.trim();

      if (token && serverModeRef.current && catalogId) {
        void (async () => {
          try {
            await removeServerWishlistItem(catalogId, token);
            const serverItems = await fetchServerWishlist(token);
            const metadata = buildWishlistMetadataMap(itemsRef.current);
            applyItems(mapServerWishlistItems(serverItems, metadata), true);
            showWishlistToast(false);
          } catch {
            removeWishlistItem(productId, catalogProductId);
            setItems(getWishlistItems());
            showWishlistToast(false);
          }
        })();
        return;
      }

      removeWishlistItem(productId, catalogProductId);
      setItems(getWishlistItems());
      showWishlistToast(false);
    },
    [applyItems],
  );

  const value = useMemo<WishlistContextValue>(
    () => ({
      items,
      ready,
      serverMode: serverModeRef.current,
      isSaved,
      toggle,
      remove,
    }),
    [items, ready, isSaved, toggle, remove],
  );

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>;
}
