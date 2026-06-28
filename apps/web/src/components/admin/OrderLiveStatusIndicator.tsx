"use client";

import type { Order } from "@/lib/types/order";
import {
  ADMIN_LIVE_STATUS_STYLES,
  getAdminLiveStatus,
} from "@/lib/admin/order-live-status";

type OrderLiveStatusIndicatorProps = {
  order: Order;
  size?: "sm" | "md";
  showLabel?: boolean;
};

export function OrderLiveStatusIndicator({
  order,
  size = "sm",
  showLabel = false,
}: OrderLiveStatusIndicatorProps) {
  const status = getAdminLiveStatus(order);
  const styles = ADMIN_LIVE_STATUS_STYLES[status];
  const dotSize = size === "sm" ? "h-2 w-2" : "h-2.5 w-2.5";

  return (
    <span
      className={`inline-flex items-center gap-1.5 ${showLabel ? "rounded-md bg-zinc-50 px-2 py-0.5 ring-1 ring-zinc-200" : ""}`}
      title={styles.label}
      aria-label={`Order status: ${styles.label}`}
    >
      <span
        className={`relative inline-flex shrink-0 ${dotSize} rounded-full ${styles.dot} ring-2 ${styles.ring}`}
        aria-hidden
      >
        {status === "processing" ? (
          <span className="absolute inset-0 animate-ping rounded-full bg-amber-400/60" />
        ) : null}
      </span>
      {showLabel ? (
        <span className="text-[11px] font-semibold text-zinc-700">{styles.label}</span>
      ) : null}
    </span>
  );
}
