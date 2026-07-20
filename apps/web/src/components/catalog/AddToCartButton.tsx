"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { Product } from "@/lib/types/catalog";
import { CartIcon } from "@/components/home/icons";
import { useAddToCart } from "@/components/cart/CartProvider";
import { useCartDrawer } from "@/lib/cart/drawer-context";
import { showProductAddedToast } from "@/lib/customer/customer-toast";
import { getCatalogProductImageSrc } from "@/lib/catalog/product-images";

interface AddToCartButtonProps {
  product: Product;
  quantity?: number;
  disabled?: boolean;
  variant?: "card" | "detail";
  className?: string;
  /** Legacy size/color/storage choice — unused for metadata configs. */
  selectedVariant?: unknown;
  configurationId?: string | null;
  configurationLabel?: string;
  configurationSku?: string;
  selectedAttributes?: Array<{ name: string; value: string; slug?: string | null }>;
  quotedUnitPrice?: number;
  compareAtUnitPrice?: number;
  stockOverride?: number;
}

export function AddToCartButton({
  product,
  quantity = 1,
  disabled = false,
  variant = "card",
  className = "",
  configurationId = null,
  configurationLabel = "",
  configurationSku,
  selectedAttributes,
  quotedUnitPrice,
  compareAtUnitPrice,
  stockOverride,
}: AddToCartButtonProps) {
  const isDisabled = disabled;
  const reduceMotion = useReducedMotion();

  const addToCart = useAddToCart(product, quantity, {
    configurationId,
    configurationLabel,
    configurationSku,
    selectedAttributes,
    quotedUnitPrice,
    compareAtUnitPrice,
    stockOverride,
    disabled: isDisabled,
  });
  const { open: openCartDrawer } = useCartDrawer();
  const [added, setAdded] = useState(false);

  const handleClick = () => {
    if (isDisabled) return;

    addToCart();
    openCartDrawer();
    setAdded(true);
    showProductAddedToast({
      productName: product.name,
      configurationLabel: configurationLabel.trim() || undefined,
      quantity,
      imageUrl: getCatalogProductImageSrc(product) || undefined,
    });
    window.setTimeout(() => setAdded(false), 2000);
  };

  const baseClasses =
    variant === "card"
      ? "inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#c9a227] via-[#d4b83d] to-[#e8c547] px-3 py-2.5 text-xs font-bold tracking-wide text-zinc-900 shadow-[0_2px_10px_rgba(201,162,39,0.28)] transition-all duration-200 ease-out hover:from-[#b8921f] hover:via-[#c9a227] hover:to-[#e0c040] hover:shadow-[0_4px_14px_rgba(201,162,39,0.35)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:from-zinc-300 disabled:via-zinc-300 disabled:to-zinc-300 disabled:text-zinc-500 disabled:shadow-none sm:py-3 sm:text-sm"
      : "relative inline-flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl border-2 border-zinc-900 bg-white py-3.5 text-sm font-semibold text-zinc-900 transition-all duration-200 hover:bg-zinc-900 hover:text-white active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white disabled:hover:text-zinc-900";

  const label = disabled
    ? configurationId === null && variant === "detail"
      ? "Select options"
      : "Out of Stock"
    : added
      ? "Added to cart"
      : "Add to Cart";

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={isDisabled}
      aria-live="polite"
      className={`${baseClasses} ${
        added
          ? "border-emerald-600 bg-emerald-600 text-white shadow-[0_4px_16px_rgba(16,185,129,0.35)] hover:bg-emerald-600 hover:text-white"
          : ""
      } ${variant === "detail" ? "flex-1" : ""} ${className}`}
    >
      <AnimatePresence mode="wait" initial={false}>
        <motion.span
          key={label}
          initial={reduceMotion ? false : { opacity: 0, y: 6 }}
          animate={{ opacity: 1, y: 0 }}
          exit={reduceMotion ? undefined : { opacity: 0, y: -6 }}
          transition={{ duration: 0.18 }}
          className="inline-flex items-center gap-2"
        >
          <CartIcon className={`h-4 w-4 shrink-0 ${added ? "animate-pulse" : ""}`} />
          {label}
        </motion.span>
      </AnimatePresence>
    </button>
  );
}
