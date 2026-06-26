"use client";

import type { Order } from "@/lib/types/order";
import { getShippingStatusLabel } from "@/lib/payment/order-filters";
import { getMethodByCode } from "@/lib/shipping/engine";
import { formatDays } from "@/lib/catalog/utils";
import { formatPrice } from "@/lib/catalog/utils";
import { OrderStatusBadge } from "./OrderStatusBadge";

interface ShippingStatusSummaryProps {
  order: Order;
}

export function ShippingStatusSummary({ order }: ShippingStatusSummaryProps) {
  const shippingMethods = [...new Set(order.items.map((item) => item.shippingMethod))];
  const deliveryEstimates = [
    ...new Set(order.items.map((item) => item.estimatedDeliveryDays).filter(Boolean)),
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <OrderStatusBadge status={order.status} />
        <p className="text-sm text-zinc-600">{getShippingStatusLabel(order.status)}</p>
      </div>

      <dl className="grid gap-4 sm:grid-cols-2">
        <div>
          <dt className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Shipping Methods
          </dt>
          <dd className="mt-1.5 space-y-1">
            {shippingMethods.map((code) => {
              const method = getMethodByCode(code);
              return (
                <p key={code} className="text-sm font-medium text-zinc-900">
                  {method ? `${method.icon} ${method.name}` : code}
                </p>
              );
            })}
          </dd>
        </div>

        <div>
          <dt className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Delivery Estimate
          </dt>
          <dd className="mt-1.5 text-sm font-medium text-zinc-900">
            {deliveryEstimates.length > 0
              ? deliveryEstimates.map((estimate) => formatDays(estimate)).join(", ")
              : "—"}
          </dd>
        </div>

        <div>
          <dt className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Shipping Cost
          </dt>
          <dd className="mt-1.5 text-sm font-semibold text-zinc-900">
            {formatPrice(order.totals.shippingTotal)}
          </dd>
        </div>

        <div>
          <dt className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Destination
          </dt>
          <dd className="mt-1.5 text-sm text-zinc-700">
            {order.shippingAddress.city}, {order.shippingAddress.region}
          </dd>
        </div>
      </dl>
    </div>
  );
}
