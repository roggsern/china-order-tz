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
  createConfigurationCartItemId,
  createSavedItemId,
  productToCartSnapshot,
} from "@/lib/cart/utils";
import { validateCartAgainstCatalog } from "@/lib/cart/validation";
import { fetchClientCatalogProducts } from "@/lib/catalog/client-catalog";
import { productService } from "@/lib/services/product-service.client";
import { applyCartItemShipping } from "@/lib/cart/shipping";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { isAdminPath, isPostCheckoutPath } from "@/lib/checkout/routes";
import { PRODUCTS_UPDATED_EVENT } from "@/lib/admin/product-storage";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  addServerCartItem,
  clearServerCartEngine,
  fetchServerCart,
  isServerCartItemId,
  mapServerCartItems,
  removeServerCartItem,
  updateServerCartItemQuantity,
} from "@/lib/api/customer-cart";
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
  const serverModeRef = useRef(false);
  itemsRef.current = state.items;

  const applyServerCart = useCallback((serverItems: CartLineItem[], prev: CartState): CartState => {
    serverModeRef.current = true;
    return normalizeCartState({
      ...prev,
      items: serverItems,
      discount: 0,
    });
  }, []);

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

    const hydrateCart = async () => {
      let products;

      try {
        products = await fetchClientCatalogProducts();
      } catch {
        products = await productService.list();
      }

      const validated = validateCartAgainstCatalog(loaded, products);
      const token = getCustomerApiToken();

      if (!token) {
        serverModeRef.current = false;
        setState(validated);
        setIsHydrated(true);
        return;
      }

      try {
        let serverCart = await fetchServerCart(token);

        if ((serverCart.items?.length ?? 0) === 0) {
          const syncable = validated.items.filter((item) => item.catalogProductId);

          for (const item of syncable) {
            await addServerCartItem(
              {
                productId: item.catalogProductId!,
                productVariantId: item.configurationId ?? null,
                quantity: item.quantity,
              },
              token,
            );
          }

          if (syncable.length > 0) {
            serverCart = await fetchServerCart(token);
          }
        }

        if ((serverCart.items?.length ?? 0) > 0) {
          const mapped = mapServerCartItems(serverCart);
          const next = applyServerCart(mapped, validated);
          persistState(next);
          setState(next);
        } else {
          serverModeRef.current = false;
          setState(validated);
        }
      } catch {
        serverModeRef.current = false;
        setState(validated);
      }

      setIsHydrated(true);
    };

    void hydrateCart();
  }, [applyServerCart]);

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

      void (async () => {
        let products;

        try {
          products = await fetchClientCatalogProducts();
        } catch {
          products = await productService.list({ refresh: true });
        }

        setState((prev) => {
          const next = validateCartAgainstCatalog(prev, products);
          persistState(next);
          return next;
        });
      })();
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
    ({
      product,
      quantity = 1,
      variant,
      configurationId = null,
      configurationLabel,
      configurationSku,
      selectedAttributes,
      quotedUnitPrice,
      compareAtUnitPrice,
      stockOverride,
    }: AddToCartInput) => {
      const stockLimit = stockOverride ?? product.stock;
      if (stockLimit <= 0) {
        return;
      }

      clearCheckoutDraft();

      const normalizedVariant = normalizeVariantChoice(variant);
      if (!configurationId && !canAddProductToCart(product, normalizedVariant)) {
        return;
      }

      const token = getCustomerApiToken();
      const catalogProductId = product.catalogProductId?.trim();

      if (token && catalogProductId) {
        void (async () => {
          try {
            const serverCart = await addServerCartItem(
              {
                productId: catalogProductId,
                productVariantId: configurationId ?? null,
                quantity: clampQuantity(quantity, stockLimit),
              },
              token,
            );
            updateState((prev) => applyServerCart(mapServerCartItems(serverCart), prev));
          } catch {
            // Fall through to local cart so the shopper still sees the item.
            const snapshot = productToCartSnapshot(product, {
              variant: normalizedVariant,
              configurationId,
              configurationLabel,
              configurationSku,
              selectedAttributes,
              quotedUnitPrice,
              compareAtUnitPrice,
              stockOverride,
            });
            const nextQuantity = clampQuantity(quantity, stockLimit);

            updateState((prev) => {
              const existing = prev.items.find((item) =>
                cartItemsMatch(item, {
                  productId: product.id,
                  variant: normalizedVariant,
                  configurationId,
                }),
              );

              if (existing) {
                const mergedQuantity = clampQuantity(
                  existing.quantity + nextQuantity,
                  stockLimit,
                );
                return {
                  ...prev,
                  items: prev.items.map((item) =>
                    item.id === existing.id
                      ? applyCartItemShipping({
                          ...existing,
                          ...snapshot,
                          quantity: mergedQuantity,
                          shippingMethod: existing.shippingMethod,
                        })
                      : item,
                  ),
                };
              }

              return {
                ...prev,
                items: [
                  ...prev.items,
                  withShipping({
                    id: configurationId
                      ? createConfigurationCartItemId(product.id, configurationId)
                      : createCartItemId(product.id, normalizedVariant),
                    ...snapshot,
                    quantity: nextQuantity,
                    addedAt: new Date().toISOString(),
                  }),
                ],
              };
            });
          }
        })();
        return;
      }

      const snapshot = productToCartSnapshot(product, {
        variant: normalizedVariant,
        configurationId,
        configurationLabel,
        configurationSku,
        selectedAttributes,
        quotedUnitPrice,
        compareAtUnitPrice,
        stockOverride,
      });
      const nextQuantity = clampQuantity(quantity, stockLimit);

      updateState((prev) => {
        const existing = prev.items.find((item) =>
          cartItemsMatch(item, {
            productId: product.id,
            variant: normalizedVariant,
            configurationId,
          }),
        );

        if (existing) {
          const mergedQuantity = clampQuantity(existing.quantity + nextQuantity, stockLimit);
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
          id: configurationId
            ? createConfigurationCartItemId(product.id, configurationId)
            : createCartItemId(product.id, normalizedVariant),
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
    [applyServerCart, updateState],
  );

  const updateQuantity = useCallback(
    (itemId: string, quantity: number) => {
      const token = getCustomerApiToken();
      const shouldSyncServer =
        Boolean(token) && (serverModeRef.current || isServerCartItemId(itemId));

      if (shouldSyncServer && token) {
        void (async () => {
          try {
            const serverCart =
              quantity <= 0
                ? await removeServerCartItem(itemId, token)
                : await updateServerCartItemQuantity(
                    itemId,
                    clampQuantity(
                      quantity,
                      itemsRef.current.find((entry) => entry.id === itemId)?.stock ?? quantity,
                    ),
                    token,
                  );
            updateState((prev) => applyServerCart(mapServerCartItems(serverCart), prev));
          } catch {
            // Keep optimistic local update when the API is unavailable.
          }
        })();
      }

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
    [applyServerCart, updateState],
  );

  const updateLinePricing = useCallback(
    (itemId: string, pricing: { unitPrice: number; compareAtUnitPrice?: number }) => {
      updateState((prev) => ({
        ...prev,
        items: prev.items.map((entry) => {
          if (entry.id !== itemId) return entry;
          const unitPrice = Number.isFinite(pricing.unitPrice)
            ? pricing.unitPrice
            : entry.unitPrice;
          const compareAt =
            typeof pricing.compareAtUnitPrice === "number" &&
            Number.isFinite(pricing.compareAtUnitPrice) &&
            pricing.compareAtUnitPrice > unitPrice
              ? pricing.compareAtUnitPrice
              : pricing.compareAtUnitPrice === undefined
                ? entry.compareAtUnitPrice
                : undefined;

          return applyCartItemShipping({
            ...entry,
            unitPrice,
            compareAtUnitPrice: compareAt,
          });
        }),
      }));
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
      const token = getCustomerApiToken();
      if (token && (serverModeRef.current || isServerCartItemId(itemId))) {
        void (async () => {
          try {
            const serverCart = await removeServerCartItem(itemId, token);
            updateState((prev) => applyServerCart(mapServerCartItems(serverCart), prev));
          } catch {
            // Local removal still applied below.
          }
        })();
      }

      updateState((prev) => ({
        ...prev,
        items: prev.items.filter((item) => item.id !== itemId),
      }));
    },
    [applyServerCart, updateState],
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
          catalogProductId: item.catalogProductId,
          slug: item.slug,
          name: item.name,
          unitPrice: item.unitPrice,
          compareAtUnitPrice: item.compareAtUnitPrice,
          origin: item.origin,
          brand: item.brand,
          brandSlug: item.brandSlug,
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
          configurationId: item.configurationId,
          configurationLabel: item.configurationLabel,
          configurationSku: item.configurationSku,
          selectedAttributes: item.selectedAttributes,
          unitShippingCost: item.unitShippingCost,
          shippingMethod: item.shippingMethod,
          shippingCost: item.shippingCost,
          estimatedDeliveryDays: item.estimatedDeliveryDays,
          shippingOptions: item.shippingOptions,
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
          id: savedItem.configurationId
            ? createConfigurationCartItemId(savedItem.productId, savedItem.configurationId)
            : createCartItemId(savedItem.productId, savedItem.variant),
          productId: savedItem.productId,
          catalogProductId: savedItem.catalogProductId,
          slug: savedItem.slug,
          name: savedItem.name,
          unitPrice: savedItem.unitPrice,
          compareAtUnitPrice: savedItem.compareAtUnitPrice,
          origin: savedItem.origin,
          brand: savedItem.brand,
          brandSlug: savedItem.brandSlug,
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
          configurationId: savedItem.configurationId,
          configurationLabel: savedItem.configurationLabel,
          configurationSku: savedItem.configurationSku,
          selectedAttributes: savedItem.selectedAttributes,
          shippingOptions: savedItem.shippingOptions,
          quantity: nextQuantity,
          addedAt: new Date().toISOString(),
          shippingMethod: savedItem.shippingMethod,
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
    const token = getCustomerApiToken();
    if (token && serverModeRef.current) {
      void clearServerCartEngine(token).catch(() => undefined);
    }

    updateState((prev) => ({
      ...prev,
      items: [],
      discount: 0,
    }));
  }, [updateState]);

  const clearCart = useCallback(() => {
    const token = getCustomerApiToken();
    if (token && (serverModeRef.current || itemsRef.current.some((item) => isServerCartItemId(item.id)))) {
      void (async () => {
        try {
          const serverCart = await clearServerCartEngine(token);
          updateState((prev) =>
            applyServerCart(mapServerCartItems(serverCart), {
              ...prev,
              savedForLater: [],
              discount: 0,
            }),
          );
        } catch {
          updateState(() => EMPTY_CART_STATE);
        }
      })();
      return;
    }

    serverModeRef.current = false;
    updateState(() => EMPTY_CART_STATE);
  }, [applyServerCart, updateState]);

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
      updateLinePricing,
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
      updateLinePricing,
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
    configurationId?: string | null;
    configurationLabel?: string;
    configurationSku?: string;
    selectedAttributes?: AddToCartInput["selectedAttributes"];
    quotedUnitPrice?: number;
    compareAtUnitPrice?: number;
    stockOverride?: number;
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
      configurationId: options?.configurationId,
      configurationLabel: options?.configurationLabel,
      configurationSku: options?.configurationSku,
      selectedAttributes: options?.selectedAttributes,
      quotedUnitPrice: options?.quotedUnitPrice,
      compareAtUnitPrice: options?.compareAtUnitPrice,
      stockOverride: options?.stockOverride,
    });
  }, [
    addToCart,
    product,
    quantity,
    options?.variant,
    options?.configurationId,
    options?.configurationLabel,
    options?.configurationSku,
    options?.selectedAttributes,
    options?.quotedUnitPrice,
    options?.compareAtUnitPrice,
    options?.stockOverride,
    options?.disabled,
  ]);
}
