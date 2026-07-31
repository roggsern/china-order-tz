"use client";

import { useWishlistContext } from "@/lib/wishlist/context";

export function useWishlist() {
  return useWishlistContext();
}
