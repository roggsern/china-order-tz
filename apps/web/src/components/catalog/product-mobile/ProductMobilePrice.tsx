"use client";

import { motion } from "framer-motion";
import { calculateDiscount, formatPrice } from "@/lib/catalog/utils";
import { RatingStars } from "../RatingStars";

interface ProductMobilePriceProps {
  price: number;
  oldPrice: number;
  rating: number;
  reviewCount: number;
}

export function ProductMobilePrice({
  price,
  oldPrice,
  rating,
  reviewCount,
}: ProductMobilePriceProps) {
  const discount = calculateDiscount(price, oldPrice);

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35, delay: 0.05 }}
      className="rounded-2xl border border-zinc-100 bg-gradient-to-br from-zinc-50 to-white p-4 shadow-sm"
    >
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-400">
            Price
          </p>
          <div className="mt-1 flex flex-wrap items-baseline gap-2">
            <span className="text-3xl font-bold tracking-tight text-red-600">
              {formatPrice(price)}
            </span>
            {oldPrice > price && (
              <span className="text-base text-zinc-400 line-through">{formatPrice(oldPrice)}</span>
            )}
          </div>
        </div>

        {discount > 0 && (
          <span className="rounded-full bg-red-600 px-3 py-1 text-sm font-bold text-white shadow-sm">
            -{discount}%
          </span>
        )}
      </div>

      <div className="mt-3 flex items-center gap-2 border-t border-zinc-100 pt-3">
        <RatingStars rating={rating} size="md" showValue reviewCount={reviewCount} />
      </div>
    </motion.div>
  );
}
