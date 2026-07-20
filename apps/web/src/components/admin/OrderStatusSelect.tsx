"use client";

import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { isAdminLocalOrderAuthorityEnabled } from "@/lib/config/env";

/** Backend OrderStatus values only — specialist packed/in_transit are not lifecycle writes. */
const BACKEND_ORDER_STATUSES: { value: OrderStatus; label: string }[] = [
  { value: ORDER_STATUS.PENDING, label: "Pending" },
  { value: ORDER_STATUS.PENDING_PAYMENT, label: "Pending payment" },
  { value: ORDER_STATUS.PAID, label: "Paid" },
  { value: ORDER_STATUS.CONFIRMED, label: "Confirmed" },
  { value: ORDER_STATUS.PROCESSING, label: "Processing" },
  { value: ORDER_STATUS.SHIPPED, label: "Shipped" },
  { value: ORDER_STATUS.DELIVERED, label: "Delivered" },
  { value: ORDER_STATUS.COMPLETED, label: "Completed" },
  { value: ORDER_STATUS.CANCELLED, label: "Cancelled" },
  { value: ORDER_STATUS.REFUND_PENDING, label: "Refund in progress" },
  { value: ORDER_STATUS.REFUNDED, label: "Refunded" },
];

const SPECIALIST_LABELS: Partial<Record<OrderStatus, string>> = {
  [ORDER_STATUS.PACKED]: "Packed (warehouse)",
  [ORDER_STATUS.IN_TRANSIT]: "In transit (shipment)",
};

interface OrderStatusSelectProps {
  value: OrderStatus;
  onChange: (status: OrderStatus) => void;
  disabled?: boolean;
  className?: string;
}

function labelFor(status: OrderStatus): string {
  return (
    BACKEND_ORDER_STATUSES.find((o) => o.value === status)?.label ??
    SPECIALIST_LABELS[status] ??
    `Unknown (${status})`
  );
}

export function OrderStatusSelect({
  value,
  onChange,
  disabled,
  className = "",
}: OrderStatusSelectProps) {
  const localAuthority = isAdminLocalOrderAuthorityEnabled();
  const readOnly = disabled || !localAuthority;

  if (readOnly) {
    return (
      <span
        className={`inline-flex rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs font-semibold text-zinc-700 ${className}`}
        aria-label="Order status (authoritative)"
        title="Status is controlled by Laravel OrderLifecycleEngine"
      >
        {labelFor(value)}
      </span>
    );
  }

  const options = BACKEND_ORDER_STATUSES.some((o) => o.value === value)
    ? BACKEND_ORDER_STATUSES
    : [...BACKEND_ORDER_STATUSES, { value, label: labelFor(value) }];

  return (
    <select
      value={value}
      disabled={disabled}
      onChange={(event) => onChange(event.target.value as OrderStatus)}
      className={`rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-700 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
      aria-label="Update order status (demo local authority only)"
    >
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}
