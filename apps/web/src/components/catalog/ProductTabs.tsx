"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type {
  CustomerReview,
  ProductOrigin,
  ProductSpecification,
  ProductShippingContext,
} from "@/lib/types/catalog";
import { formatDays, formatDeliveryWindow, formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import { getProductShippingOptions, getDeliveryOptions } from "@/lib/catalog/delivery";
import { ProductReviewsPanel } from "./ProductReviewsPanel";
import { ProductOriginBanner } from "./ProductOriginBanner";

type TabId = "description" | "specifications" | "shipping" | "reviews";

interface ProductTabsProps {
  description: string;
  features: string[];
  specifications: ProductSpecification[];
  reviews: CustomerReview[];
  reviewCount: number;
  averageRating: number;
  shippingContext?: ProductShippingContext;
  origin?: ProductOrigin;
  layout?: "default" | "below-gallery";
}

const tabs: { id: TabId; label: string }[] = [
  { id: "description", label: "Description" },
  { id: "specifications", label: "Specifications" },
  { id: "shipping", label: "Shipping" },
  { id: "reviews", label: "Reviews" },
];

export function ProductTabs({
  description,
  features,
  specifications,
  reviews,
  reviewCount,
  averageRating,
  shippingContext,
  origin = "china",
  layout = "default",
}: ProductTabsProps) {
  const reduceMotion = useReducedMotion();
  const [activeTab, setActiveTab] = useState<TabId>("description");
  const isBelowGallery = layout === "below-gallery";

  return (
    <section
      className={
        isBelowGallery
          ? "border-t border-zinc-100"
          : "mt-12 border-t border-zinc-100 pt-10"
      }
    >
      <div className="flex gap-1 overflow-x-auto border-b border-zinc-100 pb-px">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => setActiveTab(tab.id)}
            className={`shrink-0 border-b-2 px-5 py-3 text-sm font-semibold transition ${
              activeTab === tab.id
                ? "border-[#c9a227] text-zinc-900"
                : "border-transparent text-zinc-500 hover:text-zinc-800"
            }`}
          >
            {tab.label}
            {tab.id === "reviews" && ` (${reviewCount.toLocaleString()})`}
          </button>
        ))}
      </div>

      <div className={isBelowGallery ? "pt-5" : "pt-8"}>
        <AnimatePresence mode="wait" initial={false}>
          <motion.div
            key={activeTab}
            initial={reduceMotion ? false : { opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={reduceMotion ? undefined : { opacity: 0, y: -8 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
          >
            {activeTab === "description" && (
              <div className="max-w-3xl space-y-6">
                <p className="text-base leading-relaxed text-zinc-600">{description}</p>
                {features.length > 0 && (
                  <ul className="grid gap-3 sm:grid-cols-2">
                    {features.map((feature) => (
                      <li key={feature} className="flex items-center gap-2.5 text-sm text-zinc-600">
                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#c9a227]/10 text-[10px] text-[#8b6914]">
                          ✓
                        </span>
                        {feature}
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            )}

            {activeTab === "specifications" && (
              <div className="max-w-3xl overflow-hidden rounded-2xl border border-zinc-100">
                <dl className="divide-y divide-zinc-100">
                  {specifications.map((spec) => (
                    <div
                      key={spec.label}
                      className="grid gap-1 px-5 py-4 sm:grid-cols-[200px_1fr]"
                    >
                      <dt className="text-sm font-semibold text-zinc-900">{spec.label}</dt>
                      <dd className="text-sm text-zinc-600">{spec.value}</dd>
                    </div>
                  ))}
                </dl>
              </div>
            )}

            {activeTab === "shipping" && shippingContext && (
              <div className="max-w-3xl space-y-4">
                <ProductOriginBanner origin={origin} />
                <ShippingTabPanel shippingContext={shippingContext} origin={origin} />
              </div>
            )}

            {activeTab === "reviews" && (
              <ProductReviewsPanel
                reviews={reviews}
                reviewCount={reviewCount}
                averageRating={averageRating}
              />
            )}
          </motion.div>
        </AnimatePresence>
      </div>
    </section>
  );
}

function ShippingTabPanel({
  shippingContext,
  origin,
}: {
  shippingContext: ProductShippingContext;
  origin: ProductOrigin;
}) {
  if (origin === "china") {
    const options = getProductShippingOptions(shippingContext);

    return (
      <div className="max-w-3xl space-y-4">
        <div className="grid gap-3 sm:grid-cols-2">
          {options.map((option) => (
            <div
              key={option.label}
              className="rounded-2xl border border-zinc-100 bg-zinc-50/60 p-5"
            >
              <div className="flex items-start gap-3">
                <span className="text-xl">{option.icon}</span>
                <div>
                  <p className="font-semibold text-zinc-900">{option.name}</p>
                  <p className="mt-1 text-base font-bold text-[#8b6914]">
                    {option.shippingCost === null
                      ? LOCAL_DELIVERY_NEGOTIATED_LABEL
                      : formatPrice(option.shippingCost)}
                  </p>
                  <p className="mt-1 text-sm text-zinc-600">
                    {formatDeliveryWindow(option.deliveryDays)}
                  </p>
                </div>
              </div>
            </div>
          ))}
        </div>
        <p className="text-sm leading-relaxed text-zinc-500">
          Includes customs support and tracking from warehouse to Tanzania. Final shipping may vary
          slightly based on weight and destination.
        </p>
      </div>
    );
  }

  const options = getDeliveryOptions("tz");

  return (
    <div className="max-w-3xl space-y-3">
      <div className="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5">
        <p className="font-semibold text-[#8b6914]">{LOCAL_DELIVERY_NEGOTIATED_LABEL}</p>
        <p className="mt-1 text-sm text-zinc-600">
          Local delivery cost is confirmed with you before dispatch.
        </p>
      </div>
      {options.map((option) => (
        <div
          key={option.label}
          className="flex items-start gap-3 rounded-2xl border border-zinc-100 bg-zinc-50/60 p-5"
        >
          <span className="text-xl">{option.icon}</span>
          <div>
            <p className="font-semibold text-zinc-900">{option.label}</p>
            <p className="text-sm text-zinc-600">{option.detail}</p>
            {option.subdetail && (
              <p className="text-sm font-semibold text-[#8b6914]">{formatDays(option.subdetail)}</p>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
