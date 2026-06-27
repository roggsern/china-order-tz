"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { CustomerReview, ProductSpecification } from "@/lib/types/catalog";
import { ChevronDownIcon } from "@/components/home/icons";
import { RatingStars } from "../RatingStars";
import { formatDays, formatPrice } from "@/lib/catalog/utils";
import { getProductShippingOptions, getDeliveryOptions } from "@/lib/catalog/delivery";
import type { ProductOrigin, ProductShippingContext } from "@/lib/types/catalog";

type AccordionId = "description" | "specifications" | "shipping" | "reviews" | "returns";

interface AccordionItem {
  id: AccordionId;
  label: string;
  badge?: string;
}

interface ProductMobileAccordionProps {
  description: string;
  features: string[];
  specifications: ProductSpecification[];
  reviews: CustomerReview[];
  reviewCount: number;
  averageRating: number;
  shippingContext: ProductShippingContext;
  origin: ProductOrigin;
}

const RETURN_POLICY = [
  "Items may be returned within 7 days of delivery if unused and in original packaging.",
  "Refunds are processed within 5–10 business days after inspection.",
  "Custom or personalized orders may not be eligible for return.",
  "Contact our support team for return authorization before shipping items back.",
];

export function ProductMobileAccordion({
  description,
  features,
  specifications,
  reviews,
  reviewCount,
  averageRating,
  shippingContext,
  origin,
}: ProductMobileAccordionProps) {
  const reduceMotion = useReducedMotion();
  const [openId, setOpenId] = useState<AccordionId | null>("description");

  const items: AccordionItem[] = [
    { id: "description", label: "Description" },
    { id: "specifications", label: "Specifications" },
    { id: "shipping", label: "Shipping" },
    { id: "reviews", label: "Reviews", badge: reviewCount.toLocaleString() },
    { id: "returns", label: "Return Policy" },
  ];

  const toggle = (id: AccordionId) => {
    setOpenId((current) => (current === id ? null : id));
  };

  return (
    <div className="divide-y divide-zinc-100 rounded-2xl border border-zinc-100 bg-white shadow-sm">
      {items.map((item) => {
        const isOpen = openId === item.id;

        return (
          <div key={item.id}>
            <button
              type="button"
              onClick={() => toggle(item.id)}
              className="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left"
              aria-expanded={isOpen}
            >
              <span className="flex items-center gap-2">
                <span className="text-[15px] font-semibold text-zinc-900">{item.label}</span>
                {item.badge && (
                  <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-500">
                    {item.badge}
                  </span>
                )}
              </span>
              <ChevronDownIcon
                className={`h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 ${
                  isOpen ? "rotate-180" : ""
                }`}
              />
            </button>

            <AnimatePresence initial={false}>
              {isOpen && (
                <motion.div
                  initial={reduceMotion ? false : { height: 0, opacity: 0 }}
                  animate={{ height: "auto", opacity: 1 }}
                  exit={reduceMotion ? undefined : { height: 0, opacity: 0 }}
                  transition={{ duration: 0.25, ease: "easeInOut" }}
                  className="overflow-hidden"
                >
                  <div className="border-t border-zinc-50 px-4 pb-4 pt-1">
                    {item.id === "description" && (
                      <div className="space-y-4">
                        <p className="text-sm leading-relaxed text-zinc-600">{description}</p>
                        {features.length > 0 && (
                          <ul className="space-y-2">
                            {features.map((feature) => (
                              <li
                                key={feature}
                                className="flex items-start gap-2 text-sm text-zinc-600"
                              >
                                <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#c9a227]/15 text-[10px] text-[#8b6914]">
                                  ✓
                                </span>
                                {feature}
                              </li>
                            ))}
                          </ul>
                        )}
                      </div>
                    )}

                    {item.id === "specifications" && (
                      <dl className="divide-y divide-zinc-100 rounded-xl border border-zinc-100">
                        {specifications.map((spec) => (
                          <div key={spec.label} className="grid grid-cols-2 gap-2 px-3 py-2.5">
                            <dt className="text-xs font-semibold text-zinc-700">{spec.label}</dt>
                            <dd className="text-xs text-zinc-500">{spec.value}</dd>
                          </div>
                        ))}
                      </dl>
                    )}

                    {item.id === "shipping" && (
                      <ShippingAccordionContent
                        shippingContext={shippingContext}
                        origin={origin}
                      />
                    )}

                    {item.id === "reviews" && (
                      <div className="space-y-4">
                        <div className="flex items-center gap-4 rounded-xl bg-zinc-50 px-4 py-3">
                          <p className="text-3xl font-bold text-zinc-900">
                            {averageRating.toFixed(1)}
                          </p>
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
                              className="rounded-xl border border-zinc-100 bg-zinc-50/50 p-3.5"
                            >
                              <div className="flex items-start justify-between gap-2">
                                <div>
                                  <p className="text-sm font-semibold text-zinc-900">
                                    {review.author}
                                  </p>
                                  <p className="text-[11px] text-zinc-400">{review.date}</p>
                                </div>
                                <RatingStars rating={review.rating} size="sm" />
                              </div>
                              <h4 className="mt-2 text-sm font-medium text-zinc-800">
                                {review.title}
                              </h4>
                              <p className="mt-1 text-sm leading-relaxed text-zinc-600">
                                {review.comment}
                              </p>
                              {review.verified && (
                                <p className="mt-2 text-[11px] font-medium text-emerald-600">
                                  ✓ Verified Purchase
                                </p>
                              )}
                            </article>
                          ))}
                        </div>
                      </div>
                    )}

                    {item.id === "returns" && (
                      <ul className="space-y-2.5">
                        {RETURN_POLICY.map((line) => (
                          <li
                            key={line}
                            className="flex items-start gap-2 text-sm leading-relaxed text-zinc-600"
                          >
                            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#c9a227]" />
                            {line}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        );
      })}
    </div>
  );
}

function ShippingAccordionContent({
  shippingContext,
  origin,
}: {
  shippingContext: ProductShippingContext;
  origin: ProductOrigin;
}) {
  if (origin === "china") {
    const options = getProductShippingOptions(shippingContext);

    return (
      <ul className="space-y-3">
        {options.map((option) => (
          <li
            key={option.label}
            className="flex items-start gap-3 rounded-xl border border-zinc-100 bg-zinc-50/80 px-3.5 py-3"
          >
            <span className="text-lg">{option.icon}</span>
            <div>
              <p className="text-sm font-semibold text-zinc-900">{option.name}</p>
              <p className="text-sm font-medium text-[#8b6914]">
                {formatPrice(option.shippingCost)}
              </p>
              <p className="text-xs text-zinc-500">{formatDays(option.deliveryDays)}</p>
            </div>
          </li>
        ))}
        <li className="text-xs leading-relaxed text-zinc-500">
          All China imports include customs handling support and tracking from warehouse to Tanzania.
        </li>
      </ul>
    );
  }

  const options = getDeliveryOptions("tz");

  return (
    <ul className="space-y-3">
      {options.map((option) => (
        <li
          key={option.label}
          className="flex items-start gap-3 rounded-xl border border-zinc-100 bg-zinc-50/80 px-3.5 py-3"
        >
          <span className="text-lg">{option.icon}</span>
          <div>
            <p className="text-sm font-semibold text-zinc-900">{option.label}</p>
            <p className="text-sm text-zinc-600">{option.detail}</p>
            {option.subdetail && (
              <p className="text-xs font-medium text-[#8b6914]">{option.subdetail}</p>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}
