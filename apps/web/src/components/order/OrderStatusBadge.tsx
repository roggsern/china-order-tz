"use client";

import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS_LABELS } from "@/lib/payment/constants";

interface OrderStatusBadgeProps {
  status: OrderStatus;
  /** Customer-facing label from progress projection; overrides status label when set. */
  label?: string;
  size?: "sm" | "md";
}

/** Premium delivery / fulfilment status pills */
const STATUS_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/25",
  pending_payment: "bg-amber-50 text-amber-800 ring-amber-600/25",
  confirmed: "bg-blue-50 text-blue-800 ring-blue-600/20",
  processing: "bg-blue-50 text-blue-800 ring-blue-600/25",
  packed: "bg-indigo-50 text-indigo-800 ring-indigo-600/20",
  shipped: "bg-purple-50 text-purple-800 ring-purple-600/25",
  in_transit: "bg-purple-50 text-purple-800 ring-purple-600/20",
  delivered: "bg-green-50 text-green-800 ring-green-600/25",
  completed: "bg-green-50 text-green-800 ring-green-600/25",
  paid: "bg-green-50 text-green-800 ring-green-600/20",
  cancelled: "bg-red-50 text-red-700 ring-red-600/25",
  refunded: "bg-red-50 text-red-700 ring-red-600/20",
};

const STATUS_DOTS: Record<string, string> = {
  pending: "bg-amber-500",
  pending_payment: "bg-amber-500",
  confirmed: "bg-blue-500",
  processing: "bg-blue-500",
  packed: "bg-indigo-500",
  shipped: "bg-purple-500",
  in_transit: "bg-purple-500",
  delivered: "bg-green-500",
  completed: "bg-green-500",
  paid: "bg-green-500",
  cancelled: "bg-red-500",
  refunded: "bg-red-500",
};

const ARRIVAL_LABEL_STYLE = "bg-teal-50 text-teal-800 ring-teal-600/25";
const ARRIVAL_LABEL_DOT = "bg-teal-500";
const WAITING_LABEL_STYLE = "bg-sky-50 text-sky-800 ring-sky-600/25";
const WAITING_LABEL_DOT = "bg-sky-500";

function resolveBadgePresentation(status: OrderStatus, label: string) {
  if (label === "Arrived in Tanzania") {
    return { style: ARRIVAL_LABEL_STYLE, dot: ARRIVAL_LABEL_DOT };
  }

  if (label === "Waiting for pickup" || label === "Delivery arrangement pending") {
    return { style: WAITING_LABEL_STYLE, dot: WAITING_LABEL_DOT };
  }

  return {
    style: STATUS_STYLES[status] ?? "bg-zinc-50 text-zinc-700 ring-zinc-300/40",
    dot: STATUS_DOTS[status] ?? "bg-zinc-400",
  };
}

export function OrderStatusBadge({ status, label, size = "md" }: OrderStatusBadgeProps) {
  const resolvedLabel = label ?? ORDER_STATUS_LABELS[status] ?? status;
  const presentation = resolveBadgePresentation(status, resolvedLabel);
  const sizeClasses = size === "sm" ? "px-2 py-0.5 text-[11px]" : "px-2.5 py-1 text-xs";

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full font-semibold ring-1 ${sizeClasses} ${presentation.style}`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${presentation.dot}`} aria-hidden />
      {resolvedLabel}
    </span>
  );
}
