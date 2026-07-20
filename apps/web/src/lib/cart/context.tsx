"use client";

import { createContext, useContext } from "react";
import type {
  AddToCartInput,
  CartLineItem,
  CartTotals,
  SavedForLaterItem,
} from "@/lib/types/cart";
import type { ShippingMethodCode } from "@/lib/shipping/types";

export type CartContextValue = {
  items: CartLineItem[];
  savedForLater: SavedForLaterItem[];
  discount: number;
  totals: CartTotals;
  isHydrated: boolean;
  addToCart: (input: AddToCartInput) => void;
  updateQuantity: (itemId: string, quantity: number) => void;
  updateLinePricing: (
    itemId: string,
    pricing: { unitPrice: number; compareAtUnitPrice?: number },
  ) => void;
  updateShippingMethod: (itemId: string, methodCode: ShippingMethodCode) => void;
  removeItem: (itemId: string) => void;
  saveForLater: (itemId: string) => void;
  moveToCart: (savedItemId: string, quantity?: number) => void;
  removeSavedItem: (savedItemId: string) => void;
  clearCart: () => void;
  clearPurchasedItems: () => void;
  isInCart: (productId: number) => boolean;
};

export type CartActionsValue = Pick<
  CartContextValue,
  | "addToCart"
  | "updateQuantity"
  | "updateLinePricing"
  | "updateShippingMethod"
  | "removeItem"
  | "saveForLater"
  | "moveToCart"
  | "removeSavedItem"
  | "clearCart"
  | "clearPurchasedItems"
  | "isInCart"
>;

export type CartStateValue = Pick<
  CartContextValue,
  "items" | "savedForLater" | "discount" | "totals" | "isHydrated"
>;

export const CartContext = createContext<CartContextValue | null>(null);
export const CartStateContext = createContext<CartStateValue | null>(null);
export const CartActionsContext = createContext<CartActionsValue | null>(null);

export function useCart(): CartContextValue {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error("useCart must be used within CartProvider");
  }
  return context;
}

export function useCartState(): CartStateValue {
  const context = useContext(CartStateContext);
  if (!context) {
    throw new Error("useCartState must be used within CartProvider");
  }
  return context;
}

export function useCartActions(): CartActionsValue {
  const context = useContext(CartActionsContext);
  if (!context) {
    throw new Error("useCartActions must be used within CartProvider");
  }
  return context;
}
