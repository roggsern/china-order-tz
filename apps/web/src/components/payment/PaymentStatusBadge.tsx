"use client";

import type { PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_STATUS_LABELS } from "@/lib/payment/constants";

interface PaymentStatusBadgeProps {
  status: PaymentStatus;
  size?: "sm" | "md";
}

const STATUS_STYLES: Record<PaymentStatus, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/20",
  pending_payment: "bg-orange-50 text-orange-800 ring-orange-600/20",
  paid: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
  failed: "bg-red-50 text-red-700 ring-red-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/50",
  refunded: "bg-violet-50 text-violet-700 ring-violet-600/20",
};

const STATUS_DOTS: Record<PaymentStatus, string> = {
  pending: "bg-amber-500",
  pending_payment: "bg-orange-500",
  paid: "bg-emerald-500",
  failed: "bg-red-500",
  cancelled: "bg-zinc-400",
  refunded: "bg-violet-500",
};

export function PaymentStatusBadge({ status, size = "md" }: PaymentStatusBadgeProps) {
  const label = PAYMENT_STATUS_LABELS[status] ?? status;
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
