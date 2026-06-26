"use client";

import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS_LABELS } from "@/lib/payment/constants";

interface OrderStatusBadgeProps {
  status: OrderStatus;
  size?: "sm" | "md";
}

const STATUS_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/20",
  pending_payment: "bg-amber-50 text-amber-800 ring-amber-600/20",
  confirmed: "bg-sky-50 text-sky-700 ring-sky-600/20",
  processing: "bg-violet-50 text-violet-700 ring-violet-600/20",
  shipped: "bg-blue-50 text-blue-700 ring-blue-600/20",
  delivered: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/50",
};

const STATUS_DOTS: Record<string, string> = {
  pending: "bg-amber-500",
  pending_payment: "bg-amber-500",
  confirmed: "bg-sky-500",
  processing: "bg-violet-500",
  shipped: "bg-blue-500",
  delivered: "bg-emerald-500",
  cancelled: "bg-zinc-400",
};

export function OrderStatusBadge({ status, size = "md" }: OrderStatusBadgeProps) {
  const label = ORDER_STATUS_LABELS[status] ?? status;
  const sizeClasses = size === "sm" ? "px-2 py-0.5 text-[11px]" : "px-2.5 py-1 text-xs";

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-md font-semibold ring-1 ${sizeClasses} ${STATUS_STYLES[status]}`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${STATUS_DOTS[status]}`} aria-hidden />
      {label}
    </span>
  );
}
