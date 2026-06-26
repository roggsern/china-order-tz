"use client";

import { useState } from "react";
import type { CustomerReview, ProductSpecification } from "@/lib/types/catalog";
import { RatingStars } from "./RatingStars";

type TabId = "description" | "specifications" | "reviews";

interface ProductTabsProps {
  description: string;
  features: string[];
  specifications: ProductSpecification[];
  reviews: CustomerReview[];
  reviewCount: number;
  averageRating: number;
}

const tabs: { id: TabId; label: string }[] = [
  { id: "description", label: "Description" },
  { id: "specifications", label: "Specifications" },
  { id: "reviews", label: "Reviews" },
];

export function ProductTabs({
  description,
  features,
  specifications,
  reviews,
  reviewCount,
  averageRating,
}: ProductTabsProps) {
  const [activeTab, setActiveTab] = useState<TabId>("description");

  return (
    <section className="mt-16 border-t border-zinc-100 pt-12">
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

      <div className="animate-fade-in pt-8">
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
          <div className="overflow-hidden rounded-2xl border border-zinc-100">
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

        {activeTab === "reviews" && (
          <div className="space-y-8">
            <div className="flex flex-wrap items-center gap-4 rounded-2xl bg-zinc-50 px-6 py-5">
              <div>
                <p className="text-4xl font-bold text-zinc-900">{averageRating.toFixed(1)}</p>
                <RatingStars rating={averageRating} size="md" />
              </div>
              <p className="text-sm text-zinc-500">
                Based on {reviewCount.toLocaleString()} verified reviews
              </p>
            </div>

            <div className="space-y-5">
              {reviews.map((review) => (
                <article
                  key={review.id}
                  className="rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm transition hover:shadow-md"
                >
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-zinc-900">{review.author}</p>
                      <p className="text-xs text-zinc-400">{review.date}</p>
                    </div>
                    <RatingStars rating={review.rating} size="sm" />
                  </div>
                  <h4 className="mt-3 text-sm font-semibold text-zinc-800">{review.title}</h4>
                  <p className="mt-1.5 text-sm leading-relaxed text-zinc-600">{review.comment}</p>
                  {review.verified && (
                    <p className="mt-3 text-[11px] font-medium text-emerald-600">
                      ✓ Verified Purchase
                    </p>
                  )}
                </article>
              ))}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
