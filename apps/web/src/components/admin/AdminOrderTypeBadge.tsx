import type { AdminOrderType } from "@/lib/types/order";
import { getAdminOrderTypeLabel } from "@/lib/admin/order-list-summary";

interface AdminOrderTypeBadgeProps {
  orderType: AdminOrderType;
  className?: string;
}

export function AdminOrderTypeBadge({ orderType, className = "" }: AdminOrderTypeBadgeProps) {
  const isChina = orderType === "china";

  return (
    <span
      className={`inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ${
        isChina
          ? "bg-[#c9a227]/15 text-[#8b6914] ring-1 ring-[#c9a227]/30"
          : "bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200"
      } ${className}`}
    >
      {getAdminOrderTypeLabel(orderType)}
    </span>
  );
}
