"use client";

import type { ProductOrigin } from "@/lib/types/catalog";

interface ProductOriginBannerProps {
  origin: ProductOrigin;
  className?: string;
}

export function ProductOriginBanner({ origin, className = "" }: ProductOriginBannerProps) {
  const fromChina = origin === "china";

  return (
    <div
      className={`overflow-hidden rounded-2xl border ${
        fromChina
          ? "border-[#c9a227]/25 bg-gradient-to-br from-[#c9a227]/10 via-white to-zinc-50"
          : "border-emerald-200/70 bg-gradient-to-br from-emerald-50 via-white to-zinc-50"
      } ${className}`}
    >
      <div className="flex items-start gap-3 px-4 py-3.5">
        <span
          className={`mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg shadow-sm ring-1 ${
            fromChina
              ? "bg-white ring-[#c9a227]/20"
              : "bg-white ring-emerald-100"
          }`}
          aria-hidden
        >
          {fromChina ? "🇨🇳" : "🇹🇿"}
        </span>
        <div className="min-w-0">
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">
            Fulfilment
          </p>
          <p className="mt-0.5 text-sm font-bold text-zinc-900">
            {fromChina ? "Buy from China" : "Buy from Tanzania"}
          </p>
          <p className="mt-1 text-xs leading-relaxed text-zinc-600">
            {fromChina
              ? "Air and Sea delivery estimates below. Customs support included for Tanzania-bound orders."
              : "Local stock with faster handoff. Delivery details are confirmed with you before dispatch."}
          </p>
        </div>
      </div>
    </div>
  );
}
