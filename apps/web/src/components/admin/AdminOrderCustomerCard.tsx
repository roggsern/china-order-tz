"use client";

import type { CustomerInformation } from "@/lib/types/checkout";

interface AdminOrderCustomerCardProps {
  customer: CustomerInformation;
  orderDate: string;
}

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

export function AdminOrderCustomerCard({ customer, orderDate }: AdminOrderCustomerCardProps) {
  const fullName = `${customer.firstName} ${customer.lastName}`.trim() || "—";

  return (
    <div className="grid gap-3 sm:grid-cols-3">
      <div>
        <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-zinc-400">Customer</p>
        <p className="mt-1 text-sm font-semibold text-zinc-900">{fullName}</p>
      </div>
      <div>
        <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-zinc-400">Phone</p>
        <p className="mt-1 text-sm font-medium text-zinc-800">
          {customer.phone?.trim() ? (
            <a href={`tel:${customer.phone}`} className="hover:text-[#8b6914] hover:underline">
              {customer.phone}
            </a>
          ) : (
            "—"
          )}
        </p>
      </div>
      <div>
        <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-zinc-400">Order date</p>
        <time dateTime={orderDate} className="mt-1 block text-sm font-medium text-zinc-800">
          {formatOrderDate(orderDate)}
        </time>
      </div>
    </div>
  );
}
