"use client";

import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { Product } from "@/lib/types/catalog";
import { formatPrice } from "@/lib/catalog/utils";
import { AddToCartButton } from "../AddToCartButton";
import { BuyNowButton } from "../BuyNowButton";

interface ProductMobileStickyBarProps {
  product: Product;
  quantity: number;
  disabled?: boolean;
  configurationId?: string | null;
  configurationLabel?: string;
  configurationSku?: string;
  selectedAttributes?: Array<{ name: string; value: string; slug?: string | null }>;
  quotedUnitPrice?: number;
  compareAtUnitPrice?: number;
  lineTotal?: number | null;
  stockOverride?: number;
  needsConfiguration?: boolean;
  isOutOfStock?: boolean;
}

export function ProductMobileStickyBar({
  product,
  quantity,
  disabled = false,
  configurationId = null,
  configurationLabel = "",
  configurationSku,
  selectedAttributes,
  quotedUnitPrice,
  compareAtUnitPrice,
  lineTotal = null,
  stockOverride,
  needsConfiguration = false,
  isOutOfStock = false,
}: ProductMobileStickyBarProps) {
  const reduceMotion = useReducedMotion();
  const displayPrice =
    typeof quotedUnitPrice === "number" && Number.isFinite(quotedUnitPrice)
      ? quotedUnitPrice
      : product.price;
  const total =
    typeof lineTotal === "number" && Number.isFinite(lineTotal)
      ? lineTotal
      : displayPrice * quantity;

  return (
    <motion.div
      initial={reduceMotion ? false : { y: 80, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ type: "spring", stiffness: 380, damping: 32, delay: 0.12 }}
      className="fixed inset-x-0 bottom-0 z-[55] border-t border-zinc-100 bg-white/95 shadow-[0_-10px_36px_rgba(0,0,0,0.12)] backdrop-blur-md lg:hidden"
    >
      <div className="space-y-2.5 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div className="flex items-end justify-between gap-3">
          <div className="min-w-0">
            <AnimatePresence mode="wait">
              <motion.p
                key={needsConfiguration ? "pending" : displayPrice}
                initial={reduceMotion ? false : { opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={reduceMotion ? undefined : { opacity: 0, y: -4 }}
                className="text-lg font-bold leading-none text-red-600"
              >
                {needsConfiguration ? "—" : formatPrice(displayPrice)}
              </motion.p>
            </AnimatePresence>
            <p className="mt-1 truncate text-[11px] text-zinc-500">
              {needsConfiguration
                ? "Select options to continue"
                : isOutOfStock
                  ? "Out of stock for this configuration"
                  : configurationLabel
                    ? `${configurationLabel} · Qty ${quantity}`
                    : `Qty ${quantity}`}
            </p>
          </div>
          {!needsConfiguration && !isOutOfStock ? (
            <p className="shrink-0 text-xs font-semibold tabular-nums text-zinc-800">
              {formatPrice(total)}
            </p>
          ) : null}
        </div>

        <div className="flex min-w-0 gap-2">
          <BuyNowButton
            product={product}
            quantity={quantity}
            disabled={disabled}
            label="Buy Now"
            className="min-w-0 flex-[1.15] rounded-xl py-3 text-xs sm:text-sm"
            configurationId={configurationId}
            configurationLabel={configurationLabel}
            configurationSku={configurationSku}
            selectedAttributes={selectedAttributes}
            quotedUnitPrice={displayPrice}
            compareAtUnitPrice={compareAtUnitPrice}
            stockOverride={stockOverride}
          />
          <AddToCartButton
            product={product}
            quantity={quantity}
            variant="detail"
            disabled={disabled}
            configurationId={configurationId}
            configurationLabel={configurationLabel}
            configurationSku={configurationSku}
            selectedAttributes={selectedAttributes}
            quotedUnitPrice={displayPrice}
            compareAtUnitPrice={compareAtUnitPrice}
            stockOverride={stockOverride}
            className="min-w-0 flex-1 rounded-xl py-3 text-xs sm:text-sm"
          />
        </div>
      </div>
    </motion.div>
  );
}
