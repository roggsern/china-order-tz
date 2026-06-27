"use client";

import { motion } from "framer-motion";
import { calculateDiscount, formatPrice } from "@/lib/catalog/utils";

interface ProductMobilePriceProps {
  price: number;
  oldPrice: number;
}

export function ProductMobilePrice({ price, oldPrice }: ProductMobilePriceProps) {
  const discount = calculateDiscount(price, oldPrice);

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, delay: 0.06 }}
      className="flex flex-wrap items-end justify-between gap-3"
    >
      <div className="flex flex-wrap items-baseline gap-2">
        <span className="text-2xl font-bold tracking-tight text-red-600">{formatPrice(price)}</span>
        {oldPrice > price && (
          <span className="text-sm text-zinc-400 line-through">{formatPrice(oldPrice)}</span>
        )}
      </div>

      {discount > 0 && (
        <span className="rounded-full bg-[#c9a227] px-2.5 py-1 text-xs font-bold text-zinc-900">
          Save {discount}%
        </span>
      )}
    </motion.div>
  );
}
