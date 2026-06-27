"use client";

import { motion, useReducedMotion } from "framer-motion";
import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import { calculateDiscount, formatPrice } from "@/lib/catalog/utils";
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
  const discount = calculateDiscount(product.price, product.oldPrice);

  return (
    <motion.div
      initial={reduceMotion ? false : { y: 80, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ type: "spring", stiffness: 380, damping: 32, delay: 0.12 }}
      className="fixed inset-x-0 bottom-0 z-[55] border-t border-zinc-100 bg-white/95 shadow-[0_-8px_32px_rgba(0,0,0,0.1)] backdrop-blur-md lg:hidden"
    >
      <div className="flex items-center gap-3 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div className="min-w-0 shrink-0">
          <p className="text-lg font-bold leading-none text-red-600">{formatPrice(product.price)}</p>
          {product.oldPrice > product.price && (
            <p className="mt-0.5 text-[11px] text-zinc-400 line-through">
              {formatPrice(product.oldPrice)}
            </p>
          )}
          {discount > 0 && (
            <p className="mt-0.5 text-[10px] font-bold text-[#8b6914]">-{discount}% off</p>
          )}
        </div>

        <div className="flex min-w-0 flex-1 gap-2">
          <AddToCartButton
            product={product}
            quantity={quantity}
            variant="detail"
            disabled={disabled}
            selectedVariant={selectedVariant}
            className="min-w-0 flex-1 rounded-xl py-3 text-xs sm:text-sm"
          />
          <BuyNowButton
            disabled={disabled}
            label="Buy Now"
            className="min-w-0 flex-1 rounded-xl py-3 text-xs sm:text-sm"
          />
        </div>
      </div>
    </motion.div>
  );
}
