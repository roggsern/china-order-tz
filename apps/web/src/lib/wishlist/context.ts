"use client";

import { createContext, useContext } from "react";
import type { WishlistItem } from "@/lib/wishlist/storage";

export type WishlistContextValue = {
  items: WishlistItem[];
  ready: boolean;
  serverMode: boolean;
  isSaved: (productId: number, catalogProductId?: string) => boolean;
  toggle: (item: Omit<WishlistItem, "addedAt">) => Promise<boolean>;
  remove: (productId: number, catalogProductId?: string) => void;
};

export const WishlistContext = createContext<WishlistContextValue | null>(null);

export function useWishlistContext(): WishlistContextValue {
  const value = useContext(WishlistContext);
  if (!value) {
    throw new Error("useWishlist must be used within WishlistProvider");
  }
  return value;
}
