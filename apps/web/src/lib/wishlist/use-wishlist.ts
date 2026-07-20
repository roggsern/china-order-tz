"use client";

import { useEffect, useState } from "react";
import {
  getWishlistItems,
  isInWishlist,
  toggleWishlistItem,
  type WishlistItem,
} from "@/lib/wishlist/storage";
import { showWishlistToast } from "@/lib/customer/customer-toast";

export function useWishlist() {
  const [items, setItems] = useState<WishlistItem[]>([]);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const refresh = () => setItems(getWishlistItems());
    refresh();
    setReady(true);
    window.addEventListener("wishlist-updated", refresh);
    window.addEventListener("storage", refresh);
    return () => {
      window.removeEventListener("wishlist-updated", refresh);
      window.removeEventListener("storage", refresh);
    };
  }, []);

  return {
    items,
    ready,
    isSaved: (productId: number) => items.some((item) => item.productId === productId),
    toggle: (item: Omit<WishlistItem, "addedAt">) => {
      const added = toggleWishlistItem(item);
      showWishlistToast(added);
      return added;
    },
    check: isInWishlist,
  };
}
