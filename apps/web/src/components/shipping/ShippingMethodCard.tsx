"use client";

import type { ReactNode } from "react";
import { formatPrice } from "@/lib/catalog/utils";

export type ShippingMethodCardProps = {
  icon: string;
  title: string;
  price: number;
  unitPrice?: number;
  quantity?: number;
  deliveryLabel: string;
  isSelected: boolean;
  onSelect: () => void;
  badge?: string;
};

export function ShippingMethodCard({
  icon,
  title,
  price,
  unitPrice,
  quantity = 1,
  deliveryLabel,
  isSelected,
  onSelect,
  badge,
}: ShippingMethodCardProps) {
  const showQuantityBreakdown = quantity > 1 && unitPrice != null && unitPrice > 0;

  return (
    <button
      type="button"
      onClick={onSelect}
      aria-pressed={isSelected}
      className={`group relative flex h-full min-h-[10.5rem] min-w-0 flex-col rounded-2xl border p-4 text-left transition duration-200 sm:min-h-[11rem] sm:p-5 ${
        isSelected
          ? "border-[#c9a227] bg-gradient-to-br from-[#c9a227]/12 to-white shadow-[0_4px_20px_rgba(201,162,39,0.18)] ring-1 ring-[#c9a227]/40"
          : "border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-sm"
      }`}
    >
      {badge ? (
        <span
          className={`absolute -top-2 right-3 z-10 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
            badge === "Best Value"
              ? "bg-zinc-900 text-[#e8c547]"
              : "bg-[#c9a227] text-zinc-900"
          }`}
        >
          {badge}
        </span>
      ) : null}

      <span
        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg ${
          isSelected ? "bg-[#c9a227]/20" : "bg-zinc-100 group-hover:bg-zinc-50"
        }`}
        aria-hidden
      >
        {icon}
      </span>

      <p className="mt-3 line-clamp-2 text-sm font-semibold leading-snug text-zinc-900">{title}</p>

      <div className="mt-auto min-w-0 pt-3">
        <p
          className="truncate text-sm font-semibold tabular-nums text-[#8b6914] sm:text-base"
          title={formatPrice(price)}
        >
          {formatPrice(price)}
        </p>

        {showQuantityBreakdown ? (
          <p className="mt-0.5 truncate text-[11px] text-zinc-400">
            {formatPrice(unitPrice)} × {quantity}
          </p>
        ) : null}

        <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-500">{deliveryLabel}</p>
      </div>
    </button>
  );
}

export function ShippingMethodCardGrid({
  children,
  compact = false,
}: {
  children: ReactNode;
  compact?: boolean;
}) {
  return (
    <div
      className={`grid min-w-0 grid-cols-1 items-stretch gap-3 ${compact ? "" : "sm:grid-cols-2 sm:gap-4"}`}
    >
      {children}
    </div>
  );
}

export function ShippingMethodSectionLabel({ compact = false }: { compact?: boolean }) {
  return (
    <p
      className={`text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500 ${compact ? "" : ""}`}
    >
      Shipping method
    </p>
  );
}
