"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { CustomerReview, ProductOrigin, ProductSpecification, ProductShippingContext } from "@/lib/types/catalog";
import { formatDays, formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import { getProductShippingOptions, getDeliveryOptions } from "@/lib/catalog/delivery";
import { RatingStars } from "../RatingStars";

type TabId = "description" | "specifications" | "shipping" | "reviews";

interface ProductMobileTabsProps {
  description: string;
  features: string[];
  specifications: ProductSpecification[];
  reviews: CustomerReview[];
  reviewCount: number;
  averageRating: number;
  shippingContext: ProductShippingContext;
  origin: ProductOrigin;
}

const tabs: { id: TabId; label: string }[] = [
  { id: "description", label: "Description" },
  { id: "specifications", label: "Specs" },
  { id: "shipping", label: "Shipping" },
  { id: "reviews", label: "Reviews" },
];

export function ProductMobileTabs({
  description,
  features,
  specifications,
  reviews,
  reviewCount,
  averageRating,
  shippingContext,
  origin,
}: ProductMobileTabsProps) {
  const reduceMotion = useReducedMotion();
  const [activeTab, setActiveTab] = useState<TabId>("description");

  return (
    <section className="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm">
      <div className="flex overflow-x-auto border-b border-zinc-100 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {tabs.map((tab) => {
          const isActive = activeTab === tab.id;

          return (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={`relative shrink-0 px-4 py-3.5 text-sm font-semibold transition-colors ${
                isActive ? "text-zinc-900" : "text-zinc-500"
              }`}
              aria-selected={isActive}
              role="tab"
            >
              {tab.label}
              {tab.id === "reviews" && (
                <span className="ml-1 text-xs font-medium text-zinc-400">
                  ({reviewCount > 999 ? "999+" : reviewCount})
                </span>
              )}
              {isActive && (
                <motion.span
                  layoutId="mobile-product-tab-indicator"
                  className="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-[#c9a227]"
                  transition={{ type: "spring", stiffness: 420, damping: 32 }}
                />
              )}
            </button>
          );
        })}
      </div>

      <div className="relative min-h-[120px] p-4">
        <AnimatePresence mode="wait" initial={false}>
          <motion.div
            key={activeTab}
            initial={reduceMotion ? false : { opacity: 0, x: 12 }}
            animate={{ opacity: 1, x: 0 }}
            exit={reduceMotion ? undefined : { opacity: 0, x: -12 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
            role="tabpanel"
          >
            {activeTab === "description" && (
              <DescriptionPanel description={description} features={features} />
            )}
            {activeTab === "specifications" && (
              <SpecificationsPanel specifications={specifications} />
            )}
            {activeTab === "shipping" && (
              <ShippingPanel shippingContext={shippingContext} origin={origin} />
            )}
            {activeTab === "reviews" && (
              <ReviewsPanel
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

function DescriptionPanel({
  description,
  features,
}: {
  description: string;
  features: string[];
}) {
  return (
    <div className="space-y-4">
      <p className="text-[15px] leading-relaxed text-zinc-600">{description}</p>
      {features.length > 0 && (
        <ul className="space-y-2">
          {features.map((feature) => (
            <li key={feature} className="flex items-start gap-2.5 text-sm text-zinc-600">
              <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#c9a227]/15 text-[10px] text-[#8b6914]">
                ✓
              </span>
              {feature}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function SpecificationsPanel({
  specifications,
}: {
  specifications: ProductSpecification[];
}) {
  return (
    <dl className="divide-y divide-zinc-100 rounded-xl border border-zinc-100">
      {specifications.map((spec) => (
        <div key={spec.label} className="grid grid-cols-2 gap-3 px-3 py-3">
          <dt className="text-sm font-semibold text-zinc-800">{spec.label}</dt>
          <dd className="text-sm text-zinc-500">{spec.value}</dd>
        </div>
      ))}
    </dl>
  );
}

function ShippingPanel({
  shippingContext,
  origin,
}: {
  shippingContext: ProductShippingContext;
  origin: ProductOrigin;
}) {
  if (origin === "china") {
    const options = getProductShippingOptions(shippingContext);

    return (
      <ul className="space-y-2.5">
        {options.map((option) => (
          <li
            key={option.label}
            className="flex items-start gap-3 rounded-xl border border-zinc-100 bg-zinc-50 px-3.5 py-3"
          >
            <span className="text-lg">{option.icon}</span>
            <div>
              <p className="text-sm font-semibold text-zinc-900">{option.name}</p>
              <p className="text-sm font-bold text-[#8b6914]">
                {option.shippingCost === null
                  ? LOCAL_DELIVERY_NEGOTIATED_LABEL
                  : formatPrice(option.shippingCost)}
              </p>
              <p className="text-xs text-zinc-500">{formatDays(option.deliveryDays)}</p>
            </div>
          </li>
        ))}
        <p className="text-xs leading-relaxed text-zinc-500">
          Includes customs support and tracking from warehouse to Tanzania.
        </p>
      </ul>
    );
  }

  const options = getDeliveryOptions("tz");

  return (
    <ul className="space-y-2.5">
      <li className="rounded-xl border border-emerald-100 bg-emerald-50/60 px-3.5 py-3">
        <p className="text-sm font-semibold text-[#8b6914]">{LOCAL_DELIVERY_NEGOTIATED_LABEL}</p>
        <p className="mt-1 text-xs text-zinc-600">
          Local delivery cost is confirmed with you before dispatch.
        </p>
      </li>
      {options.map((option) => (
        <li
          key={option.label}
          className="flex items-start gap-3 rounded-xl border border-zinc-100 bg-zinc-50 px-3.5 py-3"
        >
          <span className="text-lg">{option.icon}</span>
          <div>
            <p className="text-sm font-semibold text-zinc-900">{option.label}</p>
            <p className="text-sm text-zinc-600">{option.detail}</p>
            {option.subdetail && (
              <p className="text-xs font-semibold text-[#8b6914]">{option.subdetail}</p>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}

function ReviewsPanel({
  reviews,
  reviewCount,
  averageRating,
}: {
  reviews: CustomerReview[];
  reviewCount: number;
  averageRating: number;
}) {
  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4 rounded-xl bg-zinc-50 px-4 py-3">
        <p className="text-3xl font-bold text-zinc-900">{averageRating.toFixed(1)}</p>
        <div>
          <RatingStars rating={averageRating} size="md" />
          <p className="mt-1 text-xs text-zinc-500">
            {reviewCount.toLocaleString()} verified reviews
          </p>
        </div>
      </div>

      <div className="space-y-3">
        {reviews.map((review) => (
          <article
            key={review.id}
            className="rounded-xl border border-zinc-100 bg-zinc-50/60 p-3.5"
          >
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className="text-sm font-semibold text-zinc-900">{review.author}</p>
                <p className="text-[11px] text-zinc-400">{review.date}</p>
              </div>
              <RatingStars rating={review.rating} size="sm" />
            </div>
            <h4 className="mt-2 text-sm font-medium text-zinc-800">{review.title}</h4>
            <p className="mt-1 text-sm leading-relaxed text-zinc-600">{review.comment}</p>
            {review.verified && (
              <p className="mt-2 text-[11px] font-medium text-emerald-600">✓ Verified Purchase</p>
            )}
          </article>
        ))}
      </div>
    </div>
  );
}
