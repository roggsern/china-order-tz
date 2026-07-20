"use client";

import Link from "next/link";
import { formatPrice } from "@/lib/catalog/utils";
import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import type { PaymentStatus } from "@/lib/types/payment";
import { PackageThumbnailIcon } from "@/components/account/AccountIcons";
import { OrderCommerceSourceBadge } from "@/components/storefront/OrderCommerceSourceBadge";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderStatusBadge } from "./OrderStatusBadge";

const TRACKABLE_STATUSES = new Set<OrderStatus>([
  ORDER_STATUS.PROCESSING,
  ORDER_STATUS.PACKED,
  ORDER_STATUS.SHIPPED,
  ORDER_STATUS.IN_TRANSIT,
]);

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

export type OrderOverviewCardData = {
  id: string;
  orderNumber: string;
  status: OrderStatus;
  paymentStatus: PaymentStatus;
  createdAt: string;
  grandTotal: number;
  productName: string;
  quantity: number | null;
  source?: string | null;
  imageUrl?: string | null;
  imageEmoji?: string;
  imageGradient?: string;
};

interface OrderOverviewCardProps {
  order: OrderOverviewCardData;
  className?: string;
}

export function OrderOverviewCard({ order, className = "" }: OrderOverviewCardProps) {
  const trackHref = `/track/${encodeURIComponent(order.id)}`;
  const detailsHref = `/orders/${encodeURIComponent(order.orderNumber)}`;
  const canTrack = TRACKABLE_STATUSES.has(order.status);

  return (
    <article
      className={`group overflow-hidden rounded-2xl border border-zinc-200/70 bg-white shadow-[0_2px_20px_rgba(0,0,0,0.04)] transition duration-300 hover:-translate-y-0.5 hover:border-[#c9a227]/30 hover:shadow-[0_8px_28px_rgba(201,162,39,0.12)] ${className}`}
    >
      <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:p-6">
        <div
          className={`relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br ${
            order.imageGradient ?? "from-zinc-100 to-zinc-200"
          } text-[#8b6914]`}
          aria-hidden
        >
          {order.imageUrl ? (
            // Product remote URLs vary by CDN; plain img keeps order cards lightweight.
            <img src={order.imageUrl} alt="" className="h-full w-full object-cover" />
          ) : order.imageEmoji ? (
            <span className="text-3xl">{order.imageEmoji}</span>
          ) : (
            <PackageThumbnailIcon className="h-8 w-8 opacity-80" />
          )}
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="font-mono text-sm font-bold text-zinc-900 sm:text-base">
              {order.orderNumber}
            </p>
            <OrderCommerceSourceBadge source={order.source} />
            <PaymentStatusBadge status={order.paymentStatus} size="sm" />
            <OrderStatusBadge status={order.status} size="sm" />
          </div>

          <p className="mt-2 line-clamp-2 text-sm font-semibold leading-snug text-zinc-800">
            {order.productName}
          </p>

          <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
            {order.quantity !== null ? (
              <span>
                Qty {order.quantity}
              </span>
            ) : null}
            <span className="font-bold tabular-nums text-zinc-900">
              {formatPrice(order.grandTotal)}
            </span>
            <time dateTime={order.createdAt}>{formatOrderDate(order.createdAt)}</time>
          </div>

          <div className="mt-4 flex flex-wrap gap-2">
            <Link
              href={detailsHref}
              className="inline-flex min-h-9 items-center justify-center rounded-xl bg-zinc-900 px-3.5 text-xs font-bold text-white transition hover:bg-zinc-800"
            >
              View details
            </Link>
            {canTrack ? (
              <Link
                href={trackHref}
                className="inline-flex min-h-9 items-center justify-center rounded-xl border border-zinc-200 bg-white px-3.5 text-xs font-semibold text-zinc-800 transition hover:border-[#c9a227]/40 hover:bg-[#c9a227]/5"
              >
                Track shipment
              </Link>
            ) : null}
          </div>
        </div>
      </div>
    </article>
  );
}
