"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { buildCartHydrationPlan } from "@/lib/cart/hydration";
import type {
  AddToCartInput,
  CartLineItem,
  CartState,
  SavedForLaterItem,
} from "@/lib/types/cart";
import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { normalizeVariantChoice, canAddProductToCart } from "@/lib/catalog/variants";
import { rejectNullConfigurationForVariantPathProduct } from "@/lib/cart/variant-selection-guard";
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
import {
  getCustomerCartErrorMessage,
  isCustomerCartAuthError,
  resolveCartSyncFailure,
  shouldFallbackToLocalCartOnError,
  STALE_CART_AUTH_RECOVERY_MESSAGE,
} from "@/lib/cart/sync-errors";
import type { AddToCartResult } from "@/lib/cart/add-to-cart-ui";
import { filterLocalItemsForServerSync } from "@/lib/cart/sync-local-to-server";
import { PRODUCTS_UPDATED_EVENT } from "@/lib/admin/product-storage";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { clearStaleCustomerAuth } from "@/lib/customer/clear-stale-customer-auth";
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
import { fetchShippingDurations } from "@/lib/shipping/durations";
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
  const [syncError, setSyncError] = useState<string | null>(null);
  const itemsRef = useRef(state.items);
  const serverModeRef = useRef(false);
  itemsRef.current = state.items;

  const clearSyncError = useCallback(() => {
    setSyncError(null);
  }, []);

  const applyServerCart = useCallback((serverItems: CartLineItem[], prev: CartState): CartState => {
    serverModeRef.current = true;
    setSyncError(null);
    return normalizeCartState({
      ...prev,
      items: serverItems,
      discount: 0,
    });
  }, []);

  const syncLocalCartToServer = useCallback(
    async (validated: CartState, token: string): Promise<CartState> => {
      try {
        let serverCart = await fetchServerCart(token);

        if ((serverCart.items?.length ?? 0) === 0) {
          const syncable = filterLocalItemsForServerSync(
            validated.items.filter((item) => item.catalogProductId),
            serverCart.items ?? [],
          );

          for (const item of syncable) {
            try {
              serverCart = await addServerCartItem(
                {
                  productId: item.catalogProductId!,
                  productVariantId: item.configurationId ?? null,
                  quantity: item.quantity,
                },
                token,
              );
            } catch (error) {
              if (isCustomerCartAuthError(error)) {
                clearStaleCustomerAuth();
                serverModeRef.current = false;
                throw error;
              }

              if (shouldFallbackToLocalCartOnError(error)) {
                throw error;
              }

              setSyncError(
                getCustomerCartErrorMessage(
                  error,
                  "Unable to sync your cart with the server.",
                ),
              );
              serverCart = await fetchServerCart(token);
              break;
            }
          }
        }

        if ((serverCart.items?.length ?? 0) > 0) {
          const mapped = mapServerCartItems(serverCart);
          const next = applyServerCart(mapped, validated);
          persistState(next);
          return next;
        }

        serverModeRef.current = false;
        return validated;
      } catch (error) {
        if (isCustomerCartAuthError(error)) {
          clearStaleCustomerAuth();
          serverModeRef.current = false;
          return validated;
        }

        if (shouldFallbackToLocalCartOnError(error)) {
          serverModeRef.current = false;
          return validated;
        }

        setSyncError(
          getCustomerCartErrorMessage(error, "Unable to sync your cart with the server."),
        );

        try {
          const serverCart = await fetchServerCart(token);
          if ((serverCart.items?.length ?? 0) > 0) {
            const next = applyServerCart(mapServerCartItems(serverCart), validated);
            persistState(next);
            return next;
          }
        } catch {
          // Keep local cart when server recovery fails.
        }

        serverModeRef.current = false;
        return validated;
      }
    },
    [applyServerCart],
  );

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }

    let cancelled = false;
    const loaded = loadCartState();
    const plan = buildCartHydrationPlan(window.location.pathname);

    setState(loaded);
    if (plan.markHydratedImmediately) {
      setIsHydrated(true);
    }

    if (!plan.runBackgroundSync) {
      return () => {
        cancelled = true;
      };
    }

    const hydrateCart = async () => {
      let products;

      try {
        products = await fetchClientCatalogProducts();
      } catch {
        products = await productService.list();
      }

      if (cancelled) {
        return;
      }

      await fetchShippingDurations();

      if (cancelled) {
        return;
      }

      const validated = validateCartAgainstCatalog(loaded, products);
      const token = getCustomerApiToken();

      if (!token) {
        serverModeRef.current = false;
        setState(validated);
        return;
      }

      const next = await syncLocalCartToServer(validated, token);
      if (cancelled) {
        return;
      }

      setState(next);
    };

    void hydrateCart();

    return () => {
      cancelled = true;
    };
  }, [syncLocalCartToServer]);

  useEffect(() => {
    if (!isHydrated || typeof window === "undefined") {
      return;
    }

    const onSessionUpdated = () => {
      const token = getCustomerApiToken();
      if (!token) {
        serverModeRef.current = false;
        return;
      }

      void (async () => {
        let products;

        try {
          products = await fetchClientCatalogProducts();
        } catch {
          products = await productService.list();
        }

        const validated = validateCartAgainstCatalog(loadCartState(), products);
        const next = await syncLocalCartToServer(validated, token);
        setState(next);
      })();
    };

    window.addEventListener("customer-session-updated", onSessionUpdated);
    return () => {
      window.removeEventListener("customer-session-updated", onSessionUpdated);
    };
  }, [isHydrated, syncLocalCartToServer]);

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

  const restoreServerCart = useCallback(
    async (token: string) => {
      try {
        const serverCart = await fetchServerCart(token);
        updateState((prev) => applyServerCart(mapServerCartItems(serverCart), prev));
      } catch {
        // Keep current UI state; syncError already surfaced.
      }
    },
    [applyServerCart, updateState],
  );

  const addToCart = useCallback(
    async ({
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
    }: AddToCartInput): Promise<AddToCartResult> => {
      const stockLimit = stockOverride ?? product.stock;
      if (stockLimit <= 0) {
        return { ok: false, message: "This item is out of stock." };
      }

      const variantSelectionError = rejectNullConfigurationForVariantPathProduct(
        product,
        configurationId,
      );
      if (variantSelectionError) {
        return { ok: false, message: variantSelectionError };
      }

      clearCheckoutDraft();

      const normalizedVariant = normalizeVariantChoice(variant);
      if (!configurationId && !canAddProductToCart(product, normalizedVariant)) {
        return { ok: false, message: "Unable to add item to your cart." };
      }

      const appendLocalItem = (): void => {
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
      };

      const token = getCustomerApiToken();
      const catalogProductId = product.catalogProductId?.trim();

      if (token && catalogProductId) {
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
          setSyncError(null);
          return { ok: true };
        } catch (error) {
          const resolution = resolveCartSyncFailure(
            error,
            "Unable to add item to your cart.",
          );

          if (resolution.kind === "fallback_local_stale_auth") {
            clearStaleCustomerAuth();
            serverModeRef.current = false;
            appendLocalItem();
            setSyncError(STALE_CART_AUTH_RECOVERY_MESSAGE);
            return { ok: true, recoveredFromStaleAuth: true };
          }

          if (resolution.kind === "fallback_local") {
            appendLocalItem();
            setSyncError(null);
            return { ok: true };
          }

          setSyncError(resolution.message);
          // Do not restore with a rejected Bearer — that loops 401s.
          if (!isCustomerCartAuthError(error)) {
            await restoreServerCart(token);
          }
          return { ok: false, message: resolution.message };
        }
      }

      appendLocalItem();
      return { ok: true };
    },
    [applyServerCart, restoreServerCart, updateState],
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
          } catch (error) {
            if (isCustomerCartAuthError(error)) {
              clearStaleCustomerAuth();
              serverModeRef.current = false;
              return;
            }

            if (shouldFallbackToLocalCartOnError(error)) {
              return;
            }

            setSyncError(
              getCustomerCartErrorMessage(error, "Unable to update cart quantity."),
            );
            void restoreServerCart(token);
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
    [applyServerCart, restoreServerCart, updateState],
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
          } catch (error) {
            if (isCustomerCartAuthError(error)) {
              clearStaleCustomerAuth();
              serverModeRef.current = false;
              return;
            }

            if (shouldFallbackToLocalCartOnError(error)) {
              return;
            }

            setSyncError(getCustomerCartErrorMessage(error, "Unable to remove cart item."));
            void restoreServerCart(token);
          }
        })();
      }

      updateState((prev) => ({
        ...prev,
        items: prev.items.filter((item) => item.id !== itemId),
      }));
    },
    [applyServerCart, restoreServerCart, updateState],
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
        } catch (error) {
          if (isCustomerCartAuthError(error)) {
            clearStaleCustomerAuth();
            serverModeRef.current = false;
            updateState(() => EMPTY_CART_STATE);
            return;
          }

          if (shouldFallbackToLocalCartOnError(error)) {
            updateState(() => EMPTY_CART_STATE);
            return;
          }

          setSyncError(getCustomerCartErrorMessage(error, "Unable to clear your cart."));
          void restoreServerCart(token);
        }
      })();
      return;
    }

    serverModeRef.current = false;
    updateState(() => EMPTY_CART_STATE);
  }, [applyServerCart, restoreServerCart, updateState]);

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
      syncError,
    }),
    [state.items, state.savedForLater, state.discount, totals, isHydrated, syncError],
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
      clearSyncError,
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
      clearSyncError,
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

  return useCallback(async (): Promise<AddToCartResult> => {
    if (options?.disabled) {
      return { ok: false, message: "Unable to add item to your cart." };
    }
    return addToCart({
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
