"use client";

import type { ReactNode } from "react";
import { motion } from "framer-motion";
import type { ProductOrigin, ProductShippingContext } from "@/lib/types/catalog";
import { getProductShippingOptions, getDeliveryOptions } from "@/lib/catalog/delivery";
import { formatDays } from "@/lib/catalog/utils";
import { RatingStars } from "../RatingStars";
import { StockStatus } from "../StockStatus";

interface ProductMobileQuickInfoProps {
  rating: number;
  reviewCount: number;
  stock: number;
  origin: ProductOrigin;
  shippingContext: ProductShippingContext;
}

function getDeliveryEstimateLabel(
  origin: ProductOrigin,
  shippingContext: ProductShippingContext,
): string {
  if (origin === "china") {
    const options = getProductShippingOptions(shippingContext);
    const airOption = options.find((option) => option.label === "Air Freight");
    const primary = airOption ?? options[0];

    if (primary?.deliveryDays) {
      return formatDays(primary.deliveryDays);
    }

    return "7–12 Days";
  }

  const local = getDeliveryOptions("tz")[0];
  return local.subdetail ?? "1–2 Days";
}

export function ProductMobileQuickInfo({
  rating,
  reviewCount,
  stock,
  origin,
  shippingContext,
}: ProductMobileQuickInfoProps) {
  const deliveryEstimate = getDeliveryEstimateLabel(origin, shippingContext);

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, delay: 0.04 }}
      className="grid grid-cols-3 gap-2 rounded-2xl border border-zinc-100 bg-zinc-50/80 p-3"
    >
      <InfoCell label="Rating">
        <div className="mt-1 space-y-1">
          <RatingStars rating={rating} size="sm" />
          <p className="text-[11px] font-medium text-zinc-600">
            {rating.toFixed(1)} · {reviewCount.toLocaleString()} reviews
          </p>
        </div>
      </InfoCell>

      <InfoCell label="Delivery">
        <p className="mt-1.5 text-sm font-bold leading-tight text-[#8b6914]">
          {deliveryEstimate}
        </p>
        <p className="mt-0.5 text-[10px] text-zinc-500">
          {origin === "china" ? "From China" : "Local TZ"}
        </p>
      </InfoCell>

      <InfoCell label="Availability">
        <div className="mt-1.5">
          <StockStatus stock={stock} size="sm" />
        </div>
      </InfoCell>
    </motion.div>
  );
}

function InfoCell({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-xl bg-white px-2.5 py-2.5 shadow-sm ring-1 ring-zinc-100/80">
      <p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-zinc-400">
        {label}
      </p>
      {children}
    </div>
  );
}
