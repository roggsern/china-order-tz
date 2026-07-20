"use client";

import type { ShippingMethodCode } from "@/lib/shipping/types";
import { formatDeliveryEstimate, getMethodByCode } from "@/lib/shipping/engine";
import { ShippingIcon } from "@/components/home/icons";

interface CheckoutShippingSummaryProps {
  method: ShippingMethodCode | null | undefined;
  /** Override estimate label (e.g. frozen days from line items). */
  estimatedDelivery?: string;
  className?: string;
  compact?: boolean;
}

function resolveEstimate(
  method: ShippingMethodCode | null | undefined,
  estimatedDelivery?: string,
): string {
  if (estimatedDelivery?.trim()) return estimatedDelivery.trim();
  if (!method) return "—";
  return formatDeliveryEstimate(method);
}

export function CheckoutShippingSummary({
  method,
  estimatedDelivery,
  className = "",
  compact = false,
}: CheckoutShippingSummaryProps) {
  const meta = method ? getMethodByCode(method) : undefined;
  const title = meta?.name ?? "Shipping";
  const estimate = resolveEstimate(method, estimatedDelivery);
  const icon = meta?.icon;

  return (
    <div
      className={`flex items-start gap-3 rounded-2xl border border-zinc-100 bg-zinc-50/80 ${
        compact ? "px-3 py-2.5" : "px-4 py-3.5"
      } ${className}`}
    >
      <span
        className={`flex shrink-0 items-center justify-center rounded-xl border border-[#c9a227]/20 bg-white text-[#8b6914] shadow-sm ${
          compact ? "h-9 w-9" : "h-11 w-11"
        }`}
        aria-hidden
      >
        {icon ? (
          <span className={compact ? "text-base" : "text-lg"}>{icon}</span>
        ) : (
          <ShippingIcon className={compact ? "h-4 w-4" : "h-5 w-5"} />
        )}
      </span>
      <div className="min-w-0 flex-1">
        <p className={`font-bold text-zinc-900 ${compact ? "text-sm" : "text-base"}`}>{title}</p>
        <p className="mt-0.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
          Estimated delivery
        </p>
        <p className={`font-semibold text-[#8b6914] ${compact ? "text-sm" : "text-base"}`}>
          {estimate}
        </p>
      </div>
    </div>
  );
}
