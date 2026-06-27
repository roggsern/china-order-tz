"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import type {
  AddToCartInput,
  CartLineItem,
  CartState,
  SavedForLaterItem,
} from "@/lib/types/cart";
import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { normalizeVariantChoice, canAddProductToCart } from "@/lib/catalog/variants";
import {
  CartActionsContext,
  CartContext,
  CartStateContext,
  type CartActionsValue,
  type CartContextValue,
  type CartStateValue,
} from "@/lib/cart/context";
import { useCart } from "@/lib/cart/context";
import {
  EMPTY_CART_STATE,
  loadCartState,
  normalizeCartState,
  saveCartState,
} from "@/lib/cart/storage";
import {
  calculateCartTotals,
  cartItemsMatch,
  clampQuantity,
  createCartItemId,
  createSavedItemId,
  productToCartSnapshot,
} from "@/lib/cart/utils";
import { validateCartAgainstCatalog } from "@/lib/cart/validation";
import { productService } from "@/lib/services/product-service.client";
import { applyCartItemShipping } from "@/lib/cart/shipping";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { isAdminPath, isPostCheckoutPath } from "@/lib/checkout/routes";
import { PRODUCTS_UPDATED_EVENT } from "@/lib/admin/product-storage";
import { CartDrawerProvider } from "@/lib/cart/drawer-context";
import { CartDrawer } from "./CartDrawer";

function persistState(state: CartState) {
  saveCartState(state);
}

function withShipping(
  item: Omit<
    CartLineItem,
    "shippingMethod" | "shippingCost" | "estimatedDeliveryDays" | "unitShippingCost"
  > & {
    shippingMethod?: ShippingMethodCode;
  },
): CartLineItem {
  const base: CartLineItem = {
    ...item,
    shippingMethod: item.shippingMethod ?? "sea_freight",
    unitShippingCost: 0,
    shippingCost: 0,
    estimatedDeliveryDays: "—",
  };
  return applyCartItemShipping(base);
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<CartState>(EMPTY_CART_STATE);
  const [isHydrated, setIsHydrated] = useState(false);
  const hydrationRef = useRef(false);
  const itemsRef = useRef(state.items);
  itemsRef.current = state.items;

  useEffect(() => {
    if (hydrationRef.current || typeof window === "undefined") {
      return;
    }
    hydrationRef.current = true;

    const loaded = loadCartState();
    const pathname = window.location.pathname;

    if (isAdminPath(pathname) || isPostCheckoutPath(pathname)) {
      setState(loaded);
      setIsHydrated(true);
      return;
    }

    void productService.list().then((products) => {
      setState(validateCartAgainstCatalog(loaded, products));
      setIsHydrated(true);
    });
  }, []);

  useEffect(() => {
    if (!isHydrated || typeof window === "undefined") {
      return;
    }

    const pathname = window.location.pathname;
    if (isPostCheckoutPath(pathname) || isAdminPath(pathname)) {
      return;
    }

    const revalidateCart = () => {
      if (itemsRef.current.length === 0) {
        return;
      }

      void productService.list({ refresh: true }).then((products) => {
        setState((prev) => {
          const next = validateCartAgainstCatalog(prev, products);
          persistState(next);
          return next;
        });
      });
    };

    const onProductsUpdated = () => revalidateCart();
    const onStorage = (event: StorageEvent) => {
      if (event.key === "china-order-tz-admin-products") {
        revalidateCart();
      }
    };

    window.addEventListener(PRODUCTS_UPDATED_EVENT, onProductsUpdated);
    window.addEventListener("storage", onStorage);
    return () => {
      window.removeEventListener(PRODUCTS_UPDATED_EVENT, onProductsUpdated);
      window.removeEventListener("storage", onStorage);
    };
  }, [isHydrated]);

  const updateState = useCallback((updater: (prev: CartState) => CartState) => {
    setState((prev) => {
      const next = normalizeCartState(updater(prev));
      persistState(next);
      return next;
    });
  }, []);

  const addToCart = useCallback(
    ({ product, quantity = 1, variant }: AddToCartInput) => {
      if (product.stock <= 0) {
        return;
      }

      clearCheckoutDraft();

      const normalizedVariant = normalizeVariantChoice(variant);
      if (!canAddProductToCart(product, normalizedVariant)) {
        return;
      }

      const snapshot = productToCartSnapshot(product, { variant: normalizedVariant });
      const nextQuantity = clampQuantity(quantity, product.stock);

      updateState((prev) => {
        const existing = prev.items.find((item) =>
          cartItemsMatch(item, { productId: product.id, variant: normalizedVariant }),
        );

        if (existing) {
          const mergedQuantity = clampQuantity(existing.quantity + nextQuantity, product.stock);
          const merged = applyCartItemShipping({
            ...existing,
            ...snapshot,
            quantity: mergedQuantity,
            shippingMethod: existing.shippingMethod,
          });
          return {
            ...prev,
            items: prev.items.map((item) => (item.id === existing.id ? merged : item)),
          };
        }

        const newItem = withShipping({
          id: createCartItemId(product.id, normalizedVariant),
          ...snapshot,
          quantity: nextQuantity,
          addedAt: new Date().toISOString(),
        });

        return {
          ...prev,
          items: [...prev.items, newItem],
        };
      });
    },
    [updateState],
  );

  const updateQuantity = useCallback(
    (itemId: string, quantity: number) => {
      updateState((prev) => {
        const item = prev.items.find((entry) => entry.id === itemId);
        if (!item) {
          return prev;
        }

        if (quantity <= 0) {
          return {
            ...prev,
            items: prev.items.filter((entry) => entry.id !== itemId),
          };
        }

        const nextQuantity = clampQuantity(quantity, item.stock);
        return {
          ...prev,
          items: prev.items.map((entry) =>
            entry.id === itemId
              ? applyCartItemShipping({ ...entry, quantity: nextQuantity })
              : entry,
          ),
        };
      });
    },
    [updateState],
  );

  const updateShippingMethod = useCallback(
    (itemId: string, methodCode: ShippingMethodCode) => {
      updateState((prev) => ({
        ...prev,
        items: prev.items.map((entry) =>
          entry.id === itemId
            ? applyCartItemShipping({ ...entry, shippingMethod: methodCode })
            : entry,
        ),
      }));
    },
    [updateState],
  );

  const removeItem = useCallback(
    (itemId: string) => {
      updateState((prev) => ({
        ...prev,
        items: prev.items.filter((item) => item.id !== itemId),
      }));
    },
    [updateState],
  );

  const saveForLater = useCallback(
    (itemId: string) => {
      updateState((prev) => {
        const item = prev.items.find((entry) => entry.id === itemId);
        if (!item) {
          return prev;
        }

        const savedId = createSavedItemId(item.productId, item.variant);
        const existingSaved = prev.savedForLater.find((entry) => cartItemsMatch(entry, item));

        const savedItem: SavedForLaterItem = {
          id: savedId,
          productId: item.productId,
          slug: item.slug,
          name: item.name,
          unitPrice: item.unitPrice,
          origin: item.origin,
          brand: item.brand,
          categorySlug: item.categorySlug,
          weightKg: item.weightKg,
          airCost: item.airCost,
          seaCost: item.seaCost,
          airDeliveryDays: item.airDeliveryDays,
          seaDeliveryDays: item.seaDeliveryDays,
          image: item.image,
          stock: item.stock,
          variant: item.variant,
          selectedSize: item.selectedSize,
          unitShippingCost: item.unitShippingCost,
          shippingMethod: item.shippingMethod,
          shippingCost: item.shippingCost,
          estimatedDeliveryDays: item.estimatedDeliveryDays,
          savedAt: new Date().toISOString(),
        };

        return {
          ...prev,
          items: prev.items.filter((entry) => entry.id !== itemId),
          savedForLater: existingSaved
            ? prev.savedForLater.map((entry) =>
                cartItemsMatch(entry, item) ? savedItem : entry,
              )
            : [...prev.savedForLater, savedItem],
        };
      });
    },
    [updateState],
  );

  const moveToCart = useCallback(
    (savedItemId: string, quantity = 1) => {
      clearCheckoutDraft();

      updateState((prev) => {
        const savedItem = prev.savedForLater.find((entry) => entry.id === savedItemId);
        if (!savedItem || savedItem.stock <= 0) {
          return prev;
        }

        const nextQuantity = clampQuantity(quantity, savedItem.stock);
        const existing = prev.items.find((item) => cartItemsMatch(item, savedItem));
        const remainingSaved = prev.savedForLater.filter((entry) => entry.id !== savedItemId);

        if (existing) {
          const mergedQuantity = clampQuantity(existing.quantity + nextQuantity, savedItem.stock);
          return {
            items: prev.items.map((item) =>
              cartItemsMatch(item, savedItem)
                ? applyCartItemShipping({
                    ...item,
                    ...savedItem,
                    quantity: mergedQuantity,
                    shippingMethod: item.shippingMethod,
                  })
                : item,
            ),
            savedForLater: remainingSaved,
            discount: prev.discount,
          };
        }

        const newItem = withShipping({
          id: createCartItemId(savedItem.productId, savedItem.variant),
          productId: savedItem.productId,
          slug: savedItem.slug,
          name: savedItem.name,
          unitPrice: savedItem.unitPrice,
          origin: savedItem.origin,
          brand: savedItem.brand,
          categorySlug: savedItem.categorySlug,
          weightKg: savedItem.weightKg,
          airCost: savedItem.airCost,
          seaCost: savedItem.seaCost,
          airDeliveryDays: savedItem.airDeliveryDays,
          seaDeliveryDays: savedItem.seaDeliveryDays,
          image: savedItem.image,
          stock: savedItem.stock,
          variant: savedItem.variant,
          selectedSize: savedItem.selectedSize,
          quantity: nextQuantity,
          addedAt: new Date().toISOString(),
        });

        return {
          items: [...prev.items, newItem],
          savedForLater: remainingSaved,
          discount: prev.discount,
        };
      });
    },
    [updateState],
  );

  const removeSavedItem = useCallback(
    (savedItemId: string) => {
      updateState((prev) => ({
        ...prev,
        savedForLater: prev.savedForLater.filter((item) => item.id !== savedItemId),
      }));
    },
    [updateState],
  );

  const clearPurchasedItems = useCallback(() => {
    updateState((prev) => ({
      ...prev,
      items: [],
      discount: 0,
    }));
  }, [updateState]);

  const clearCart = useCallback(() => {
    updateState(() => EMPTY_CART_STATE);
  }, [updateState]);

  const isInCart = useCallback(
    (productId: number) => itemsRef.current.some((item) => item.productId === productId),
    [],
  );

  const totals = useMemo(
    () => calculateCartTotals({ items: state.items, savedForLater: state.savedForLater, discount: state.discount }),
    [state.items, state.discount, state.savedForLater],
  );

  const stateValue = useMemo<CartStateValue>(
    () => ({
      items: state.items,
      savedForLater: state.savedForLater,
      discount: state.discount,
      totals,
      isHydrated,
    }),
    [state.items, state.savedForLater, state.discount, totals, isHydrated],
  );

  const actionsValue = useMemo<CartActionsValue>(
    () => ({
      addToCart,
      updateQuantity,
      updateShippingMethod,
      removeItem,
      saveForLater,
      moveToCart,
      removeSavedItem,
      clearCart,
      clearPurchasedItems,
      isInCart,
    }),
    [
      addToCart,
      updateQuantity,
      updateShippingMethod,
      removeItem,
      saveForLater,
      moveToCart,
      removeSavedItem,
      clearCart,
      clearPurchasedItems,
      isInCart,
    ],
  );

  const value = useMemo<CartContextValue>(
    () => ({ ...stateValue, ...actionsValue }),
    [stateValue, actionsValue],
  );

  return (
    <CartStateContext.Provider value={stateValue}>
      <CartActionsContext.Provider value={actionsValue}>
        <CartContext.Provider value={value}>
          <CartDrawerProvider>
            {children}
            <CartDrawer />
          </CartDrawerProvider>
        </CartContext.Provider>
      </CartActionsContext.Provider>
    </CartStateContext.Provider>
  );
}

export function useAddToCart(
  product: Product,
  quantity = 1,
  options?: {
    variant?: ProductVariantChoice;
    disabled?: boolean;
  },
) {
  const { addToCart } = useCart();

  return useCallback(() => {
    if (options?.disabled) return;
    addToCart({
      product,
      quantity,
      variant: options?.variant,
    });
  }, [addToCart, product, quantity, options?.variant, options?.disabled]);
}
