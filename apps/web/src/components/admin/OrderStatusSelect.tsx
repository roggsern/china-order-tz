"use client";

import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";

const ADMIN_FULFILLMENT_STATUSES: { value: OrderStatus; label: string }[] = [
  { value: ORDER_STATUS.PENDING, label: "Pending" },
  { value: ORDER_STATUS.PROCESSING, label: "Processing" },
  { value: ORDER_STATUS.SHIPPED, label: "Shipped" },
  { value: ORDER_STATUS.DELIVERED, label: "Delivered" },
];

interface OrderStatusSelectProps {
  value: OrderStatus;
  onChange: (status: OrderStatus) => void;
  disabled?: boolean;
  className?: string;
}

export function OrderStatusSelect({
  value,
  onChange,
  disabled,
  className = "",
}: OrderStatusSelectProps) {
  return (
    <select
      value={value}
      disabled={disabled}
      onChange={(event) => onChange(event.target.value as OrderStatus)}
      className={`rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-700 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
      aria-label="Update order status"
    >
      {ADMIN_FULFILLMENT_STATUSES.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}
