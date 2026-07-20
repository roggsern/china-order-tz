"use client";

import { memo, useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import type { CartLineItem } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";
import { getOriginLabel } from "@/lib/catalog/delivery";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import { getLineProductSavings, getLineTotal } from "@/lib/cart/utils";
import {
  discoverNextMoqTier,
  quoteCartLine,
  type CartMoqHint,
} from "@/lib/cart/quote";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { useCartActions } from "@/lib/cart/context";
import { CartItemShippingSelector, LocalDeliveryBadge } from "./CartItemShippingSelector";
import { CartItemShippingSummary } from "./CartItemShippingSummary";
import { CartItemQuantityControl } from "./CartItemQuantityControl";
import { CartItemConfigurationSummary } from "./CartItemConfigurationSummary";
import { MoqStatusCard } from "./CartItemMoqHint";
import { CloseIcon } from "@/components/home/icons";
import { showCartRemovedToast } from "@/lib/customer/customer-toast";

interface CartItemRowProps {
  item: CartLineItem;
}

function formatCategoryLabel(slug: string): string {
  return slug
    .split("-")
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function cartItemRowPropsAreEqual(prev: CartItemRowProps, next: CartItemRowProps): boolean {
  const a = prev.item;
  const b = next.item;

  return (
    a.id === b.id &&
    a.quantity === b.quantity &&
    a.unitPrice === b.unitPrice &&
    a.compareAtUnitPrice === b.compareAtUnitPrice &&
    a.shippingMethod === b.shippingMethod &&
    a.shippingCost === b.shippingCost &&
    a.unitShippingCost === b.unitShippingCost &&
    a.estimatedDeliveryDays === b.estimatedDeliveryDays &&
    a.stock === b.stock &&
    a.configurationLabel === b.configurationLabel &&
    a.configurationSku === b.configurationSku &&
    JSON.stringify(a.selectedAttributes ?? []) === JSON.stringify(b.selectedAttributes ?? [])
  );
}

function CartItemRowComponent({ item }: CartItemRowProps) {
  const { updateQuantity, updateLinePricing, removeItem, saveForLater } = useCartActions();
  const origin = getOriginLabel(item.origin);
  const [isUpdating, setIsUpdating] = useState(false);
  const [confirmRemove, setConfirmRemove] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [moqHint, setMoqHint] = useState<CartMoqHint | null>(null);
  const quoteRequestRef = useRef(0);

  const maxQuantity = Math.min(item.stock, 99);
  const lineTotal = getLineTotal(item);
  const itemTotal = lineTotal + (item.origin === "tz" ? 0 : item.shippingCost);
  const lineSavings = getLineProductSavings(item);
  const hasConfiguration = Boolean(item.configurationId);

  const refreshQuote = useCallback(
    async (quantity: number) => {
      if (!item.configurationId) {
        setMoqHint(null);
        return;
      }

      const requestId = ++quoteRequestRef.current;
      setIsUpdating(true);

      try {
        const priced = await quoteCartLine({
          slug: item.slug,
          configurationId: item.configurationId,
          quantity,
        });

        if (requestId !== quoteRequestRef.current) return;

        updateLinePricing(item.id, {
          unitPrice: priced.unitPrice,
          compareAtUnitPrice: priced.compareAtUnitPrice,
        });

        const hint = await discoverNextMoqTier({
          slug: item.slug,
          configurationId: item.configurationId,
          currentQuantity: quantity,
          currentUnitPrice: priced.unitPrice,
          stock: item.stock,
        });

        if (requestId !== quoteRequestRef.current) return;
        setMoqHint(hint);
      } catch {
        if (requestId === quoteRequestRef.current) {
          setMoqHint(null);
        }
      } finally {
        if (requestId === quoteRequestRef.current) {
          setIsUpdating(false);
        }
      }
    },
    [item.configurationId, item.id, item.slug, item.stock, updateLinePricing],
  );

  useEffect(() => {
    if (!hasConfiguration) return;
    void refreshQuote(item.quantity);
    // Refresh on mount / configuration identity only — qty changes call refresh explicitly.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hasConfiguration, item.configurationId, item.id]);

  useEffect(() => {
    setIsUpdating(false);
  }, [item.quantity, item.shippingCost, item.shippingMethod, item.unitPrice]);

  const handleQuantityChange = (nextQuantity: number) => {
    setErrorMessage(null);

    if (nextQuantity > maxQuantity) {
      setErrorMessage(`Only ${maxQuantity} available in stock.`);
      return;
    }

    setIsUpdating(true);
    updateQuantity(item.id, nextQuantity);

    if (hasConfiguration) {
      void refreshQuote(nextQuantity);
    } else {
      setIsUpdating(false);
    }
  };

  const handleRemove = () => {
    removeItem(item.id);
    setConfirmRemove(false);
    showCartRemovedToast(item.name);
  };

  return (
    <motion.article
      layout
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -8 }}
      transition={{ duration: 0.22, ease: "easeOut" }}
      className="rounded-2xl border border-zinc-100 bg-white p-4 shadow-[0_4px_24px_rgba(0,0,0,0.05)] sm:p-5"
    >
      <div className="flex gap-4 sm:gap-5">
        <Link
          href={`/products/${item.slug}`}
          className="block shrink-0 overflow-hidden rounded-xl border border-zinc-100 shadow-sm"
        >
          <ProductImageDisplay
            image={item.image}
            fallbackEmoji={item.image.emoji}
            fallbackGradient={item.image.gradient}
            className="h-24 w-24 sm:h-28 sm:w-28"
            emojiClassName="text-4xl"
          />
        </Link>

        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#c9a227]">
                {item.brand && <span>{item.brand}</span>}
                {item.categorySlug && item.categorySlug !== "uncategorized" && (
                  <span className="text-zinc-400">·</span>
                )}
                {item.categorySlug && item.categorySlug !== "uncategorized" && (
                  <span className="text-zinc-500">{formatCategoryLabel(item.categorySlug)}</span>
                )}
              </div>

              <Link href={`/products/${item.slug}`}>
                <h3 className="mt-1 line-clamp-2 text-base font-semibold leading-snug text-zinc-900 transition hover:text-[#8b6914]">
                  {item.name}
                </h3>
              </Link>

              <CartItemConfigurationSummary item={item} className="mt-2" />

              <p className="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-zinc-500">
                <span aria-hidden>{origin.flag}</span>
                {origin.label}
              </p>
            </div>

            {!confirmRemove ? (
              <button
                type="button"
                onClick={() => setConfirmRemove(true)}
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-zinc-400 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                aria-label={`Remove ${item.name} from cart`}
              >
                <CloseIcon className="h-4 w-4" />
              </button>
            ) : (
              <div className="flex shrink-0 flex-col items-end gap-1.5">
                <p className="text-[11px] font-medium text-zinc-600">Remove item?</p>
                <div className="flex gap-1.5">
                  <button
                    type="button"
                    onClick={handleRemove}
                    className="rounded-lg bg-red-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-red-700"
                  >
                    Yes
                  </button>
                  <button
                    type="button"
                    onClick={() => setConfirmRemove(false)}
                    className="rounded-lg border border-zinc-200 px-2.5 py-1 text-[11px] font-semibold text-zinc-700 transition hover:bg-zinc-50"
                  >
                    No
                  </button>
                </div>
              </div>
            )}
          </div>

          <div className="mt-3 flex flex-wrap items-end gap-x-3 gap-y-1">
            <p className="text-lg font-extrabold tabular-nums text-red-600">
              {formatPrice(item.unitPrice)}
            </p>
            {lineSavings > 0 &&
            typeof item.compareAtUnitPrice === "number" &&
            item.compareAtUnitPrice > item.unitPrice ? (
              <p className="pb-0.5 text-sm font-medium text-zinc-400 line-through">
                {formatPrice(item.compareAtUnitPrice)}
              </p>
            ) : null}
            <p className="pb-0.5 text-xs text-zinc-500">
              {lineSavings > 0 ? "wholesale · per unit" : "per unit"}
            </p>
          </div>
        </div>
      </div>

      <div className="mt-5 flex flex-col gap-4 border-t border-zinc-100 pt-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p className="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
            Quantity
          </p>
          <CartItemQuantityControl
            quantity={item.quantity}
            onChange={handleQuantityChange}
            max={maxQuantity}
            isUpdating={isUpdating}
          />
          {errorMessage && (
            <p className="mt-2 text-xs font-medium text-red-600" role="alert">
              {errorMessage}
            </p>
          )}
          {item.quantity >= maxQuantity && !errorMessage && (
            <p className="mt-2 text-xs text-zinc-500">Maximum quantity reached.</p>
          )}
          <MoqStatusCard
            className="mt-3 max-w-sm"
            unlocked={
              lineSavings > 0
                ? { savingsAmount: lineSavings, unitPrice: item.unitPrice }
                : null
            }
            hint={moqHint}
          />
        </div>

        <div className="sm:min-w-[14rem] sm:text-left">
          {lineSavings > 0 && typeof item.compareAtUnitPrice === "number" ? (
            <dl className="space-y-2 rounded-2xl border border-emerald-200 bg-emerald-50/50 px-4 py-3 text-sm">
              <div className="flex items-center justify-between gap-3">
                <dt className="text-zinc-600">Products (original subtotal)</dt>
                <dd className="font-medium tabular-nums text-zinc-500 line-through">
                  {formatPrice(item.compareAtUnitPrice * item.quantity)}
                </dd>
              </div>
              <div className="flex items-center justify-between gap-3">
                <dt className="font-medium text-emerald-800">Wholesale Discount</dt>
                <dd className="font-semibold tabular-nums text-emerald-700">
                  −{formatPrice(lineSavings)}
                </dd>
              </div>
              {item.origin === "china" ? (
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-zinc-600">Shipping</dt>
                  <dd className="font-medium tabular-nums text-zinc-900">
                    {formatPrice(item.shippingCost)}
                  </dd>
                </div>
              ) : null}
              <div className="flex items-center justify-between gap-3 border-t border-emerald-100 pt-2">
                <dt className="font-semibold text-zinc-800">Item total</dt>
                <dd className="text-base font-bold tabular-nums text-zinc-900">
                  {formatPrice(itemTotal)}
                </dd>
              </div>
            </dl>
          ) : (
            <>
              <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
                Item total
              </p>
              <p className="mt-1 text-xl font-bold tabular-nums text-zinc-900">
                {formatPrice(itemTotal)}
              </p>
              <p className="mt-1 text-xs text-zinc-500">
                {item.origin === "tz"
                  ? LOCAL_DELIVERY_NEGOTIATED_LABEL
                  : `Includes ${formatPrice(item.shippingCost)} shipping`}
              </p>
            </>
          )}
        </div>
      </div>

      <CartItemShippingSummary
        shippingMethod={item.shippingMethod}
        shippingCost={item.origin === "tz" ? null : item.shippingCost}
        estimatedDeliveryDays={item.estimatedDeliveryDays}
        origin={item.origin}
        className="mt-4"
      />

      {item.origin === "china" ? (
        <CartItemShippingSelector
          itemId={item.id}
          origin={item.origin}
          weightKg={item.weightKg}
          categorySlug={item.categorySlug}
          airCost={item.airCost}
          seaCost={item.seaCost}
          airDeliveryDays={item.airDeliveryDays}
          seaDeliveryDays={item.seaDeliveryDays}
          quantity={item.quantity}
          selectedMethod={item.shippingMethod}
        />
      ) : (
        <div className="mt-4">
          <LocalDeliveryBadge
            shippingMethod={item.shippingMethod}
            shippingCost={null}
            estimatedDeliveryDays={item.estimatedDeliveryDays}
          />
        </div>
      )}

      <div className="mt-4 flex flex-wrap gap-4 border-t border-zinc-100 pt-3 text-sm">
        <button
          type="button"
          onClick={() => saveForLater(item.id)}
          className="font-medium text-zinc-600 transition hover:text-[#8b6914]"
        >
          Save for later
        </button>
      </div>
    </motion.article>
  );
}

export const CartItemRow = memo(CartItemRowComponent, cartItemRowPropsAreEqual);
