"use client";

import type { Order, OrderLineItem } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { formatVariantLabel } from "@/lib/catalog/variants";
import { resolveAdminLineItemSourceBadge } from "@/lib/admin/order-source-badge";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { AdminOrderSourceBadge } from "@/components/admin/AdminOrderSourceBadge";

interface AdminOrderItemsListProps {
  order: Order;
  items: OrderLineItem[];
}

export function AdminOrderItemsList({ order, items }: AdminOrderItemsListProps) {
  return (
    <ul className="divide-y divide-zinc-100">
      {items.map((item) => {
        const lineSubtotal = (item.price ?? item.unitPrice) * item.quantity;
        const variantLines = formatVariantLabel(item.variant ?? {});
        const sourceBadge = resolveAdminLineItemSourceBadge(order, item);

        return (
          <li key={item.id} className="flex gap-3 py-3.5 first:pt-0 last:pb-0 sm:gap-4">
            <div className="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-zinc-100 bg-zinc-50 sm:h-[72px] sm:w-[72px]">
              <ProductImageDisplay
                image={item.image}
                fallbackEmoji={item.image.emoji}
                fallbackGradient={item.image.gradient}
                className="h-full w-full"
                emojiClassName="text-2xl"
              />
            </div>

            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <p className="text-sm font-semibold leading-snug text-zinc-900 sm:text-base">
                  {item.name}
                </p>
                <AdminOrderSourceBadge badge={sourceBadge} />
              </div>

              {variantLines.length > 0 && (
                <div className="mt-1 flex flex-wrap gap-1.5">
                  {variantLines.map((line) => (
                    <span
                      key={line}
                      className="inline-flex rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600"
                    >
                      {line}
                    </span>
                  ))}
                </div>
              )}

              <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                <span className="font-semibold text-zinc-700">Qty {item.quantity}</span>
                <span aria-hidden>·</span>
                <span>{formatPrice(item.price ?? item.unitPrice)} each</span>
                {item.configurationSku ? (
                  <>
                    <span aria-hidden>·</span>
                    <span className="font-mono">{item.configurationSku}</span>
                  </>
                ) : null}
                {(item.shipping?.method || item.shippingMethod) && (
                  <>
                    <span aria-hidden>·</span>
                    <span>
                      Ship: {item.shipping?.method ?? item.shippingMethod}
                      {(item.shipping?.cost ?? item.shippingCost ?? 0) > 0
                        ? ` (${formatPrice(item.shipping?.cost ?? item.shippingCost)})`
                        : ""}
                    </span>
                  </>
                )}
              </div>
            </div>

            <div className="shrink-0 text-right">
              <p className="text-sm font-bold text-zinc-900">{formatPrice(lineSubtotal)}</p>
            </div>
          </li>
        );
      })}
    </ul>
  );
}
