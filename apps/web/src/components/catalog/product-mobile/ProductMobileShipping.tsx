"use client";

import { motion } from "framer-motion";
import type { ProductOrigin, ProductShippingContext } from "@/lib/types/catalog";
import { formatDays, formatPrice } from "@/lib/catalog/utils";
import { getProductShippingOptions, getDeliveryOptions } from "@/lib/catalog/delivery";

interface ProductMobileShippingProps extends ProductShippingContext {
  origin: ProductOrigin;
}

export function ProductMobileShipping(props: ProductMobileShippingProps) {
  const chinaOptions =
    props.origin === "china" ? getProductShippingOptions(props) : [];
  const localOptions = props.origin === "tz" ? getDeliveryOptions("tz") : [];

  const cards =
    props.origin === "china"
      ? chinaOptions.map((option) => ({
          key: option.label,
          icon: option.icon,
          title: option.name,
          price: formatPrice(option.shippingCost),
          detail: formatDays(option.deliveryDays),
          highlight: option.label === "Air Freight",
        }))
      : localOptions.map((option) => ({
          key: option.label,
          icon: option.icon,
          title: option.label,
          price: option.detail,
          detail: option.subdetail ?? "",
          highlight: option.label === "Local Delivery",
        }));

  if (cards.length === 0) return null;

  return (
    <div className="space-y-3">
      <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">
        Shipping & Delivery
      </p>
      <div className="grid grid-cols-2 gap-2.5">
        {cards.map((card, index) => (
          <motion.div
            key={card.key}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: index * 0.06 }}
            className={`flex flex-col rounded-2xl border p-3.5 ${
              card.highlight
                ? "border-[#c9a227]/30 bg-[#c9a227]/5 shadow-sm"
                : "border-zinc-100 bg-white shadow-sm"
            }`}
          >
            <span
              className={`flex h-9 w-9 items-center justify-center rounded-xl text-base ${
                card.highlight ? "bg-[#c9a227]/15" : "bg-zinc-100"
              }`}
            >
              {card.icon}
            </span>
            <p className="mt-2.5 text-sm font-semibold leading-tight text-zinc-900">
              {card.title}
            </p>
            <p className="mt-1 text-sm font-bold text-[#8b6914]">{card.price}</p>
            {card.detail && <p className="mt-0.5 text-xs text-zinc-500">{card.detail}</p>}
          </motion.div>
        ))}
      </div>
    </div>
  );
}
