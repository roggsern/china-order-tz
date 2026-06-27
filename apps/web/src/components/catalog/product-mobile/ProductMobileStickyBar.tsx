"use client";

import { motion, useReducedMotion } from "framer-motion";
import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import { AddToCartButton } from "../AddToCartButton";
import { BuyNowButton } from "../BuyNowButton";

interface ProductMobileStickyBarProps {
  product: Product;
  quantity: number;
  selectedVariant: ProductVariantChoice;
  disabled?: boolean;
}

export function ProductMobileStickyBar({
  product,
  quantity,
  selectedVariant,
  disabled = false,
}: ProductMobileStickyBarProps) {
  const reduceMotion = useReducedMotion();

  return (
    <motion.div
      initial={reduceMotion ? false : { y: 80, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ type: "spring", stiffness: 380, damping: 32, delay: 0.15 }}
      className="fixed inset-x-0 bottom-0 z-[55] border-t border-zinc-100 bg-white/95 px-4 py-3 shadow-[0_-8px_32px_rgba(0,0,0,0.08)] backdrop-blur-md pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden"
    >
      <div className="flex gap-2.5">
        <AddToCartButton
          product={product}
          quantity={quantity}
          variant="detail"
          disabled={disabled}
          selectedVariant={selectedVariant}
          className="flex-1 rounded-xl py-3.5"
        />
        <BuyNowButton
          disabled={disabled}
          label="Order Now"
          className="flex-1 rounded-xl py-3.5"
        />
      </div>
    </motion.div>
  );
}
