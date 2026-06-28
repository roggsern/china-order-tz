"use client";

import Link from "next/link";
import { useMemo } from "react";
import { useAdminDeliveries } from "@/lib/delivery/use-admin-deliveries";
import { DELIVERY_STATUS_LABELS } from "@/lib/delivery/delivery-labels";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";

function formatUpdatedAt(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

export function AdminDeliveriesSection() {
  const { deliveries, isLoading } = useAdminDeliveries(true);
  const { getOrderById } = useAdminOrders();

  const rows = useMemo(
    () =>
      [...deliveries].sort((a, b) => b.updatedAt.localeCompare(a.updatedAt)).slice(0, 8),
    [deliveries],
  );

  return (
    <section className="admin-card overflow-hidden">
      <div className="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
        <div>
          <h2 className="text-sm font-bold text-zinc-900">Active deliveries</h2>
          <p className="mt-0.5 text-xs text-zinc-500">Live logistics pipeline — updates via WebSocket</p>
        </div>
        <span className="rounded-full bg-[#c9a227]/15 px-2.5 py-1 text-xs font-bold text-[#8b6914]">
          {isLoading ? "…" : rows.length}
        </span>
      </div>

      {isLoading ? (
        <div className="p-8">
          <div className="h-32 animate-pulse rounded-xl bg-zinc-50" />
        </div>
      ) : rows.length === 0 ? (
        <div className="px-6 py-12 text-center text-sm text-zinc-500">
          No active deliveries. Mark paid orders as packed to start logistics.
        </div>
      ) : (
        <>
          <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[640px] text-left text-sm">
              <thead>
                <tr className="border-b border-zinc-100 bg-zinc-50/80">
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Order
                  </th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Status
                  </th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Driver
                  </th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Updated
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {rows.map((delivery) => {
                  const order = getOrderById(delivery.orderId);
                  const customer = order
                    ? `${order.customer.firstName} ${order.customer.lastName}`.trim()
                    : "—";

                  return (
                    <tr key={delivery.deliveryId} className="hover:bg-zinc-50/80">
                      <td className="px-4 py-3">
                        <Link
                          href={`/admin/orders/${delivery.orderId}`}
                          className="font-mono text-sm font-semibold text-zinc-900 hover:text-[#8b6914]"
                        >
                          {delivery.orderNumber}
                        </Link>
                        <p className="mt-0.5 text-xs text-zinc-500">{customer}</p>
                      </td>
                      <td className="px-4 py-3">
                        <span className="inline-flex rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-700">
                          {DELIVERY_STATUS_LABELS[delivery.status]}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-sm text-zinc-700">
                        {delivery.assignedDriver ?? "—"}
                      </td>
                      <td className="px-4 py-3 text-xs text-zinc-500">
                        {formatUpdatedAt(delivery.updatedAt)}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <ul className="divide-y divide-zinc-100 md:hidden">
            {rows.map((delivery) => (
              <li key={delivery.deliveryId}>
                <Link
                  href={`/admin/orders/${delivery.orderId}`}
                  className="block px-4 py-4 transition hover:bg-zinc-50"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-mono text-sm font-semibold text-zinc-900">
                        {delivery.orderNumber}
                      </p>
                      <p className="mt-1 text-xs text-zinc-500">
                        {delivery.assignedDriver ? `Driver: ${delivery.assignedDriver}` : "No driver"}
                      </p>
                    </div>
                    <span className="rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-700">
                      {DELIVERY_STATUS_LABELS[delivery.status]}
                    </span>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        </>
      )}
    </section>
  );
}
