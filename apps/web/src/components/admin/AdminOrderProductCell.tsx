import type { Order } from "@/lib/types/order";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { AdminOrderTypeBadge } from "@/components/admin/AdminOrderTypeBadge";
import {
  formatAdminOrderProductTooltip,
  getAdminOrderListSummary,
} from "@/lib/admin/order-list-summary";

interface AdminOrderProductCellProps {
  order: Order;
  compact?: boolean;
  showSource?: boolean;
}

export function AdminOrderProductCell({
  order,
  compact = false,
  showSource = true,
}: AdminOrderProductCellProps) {
  const summary = getAdminOrderListSummary(order);
  const tooltip = formatAdminOrderProductTooltip(order);

  return (
    <div
      className="group relative flex min-w-0 items-start gap-2.5"
      title={tooltip}
      aria-label={tooltip.replace(/\n/g, ", ")}
    >
      <div
        className={`shrink-0 overflow-hidden rounded-lg ring-1 ring-zinc-200/80 ${
          compact ? "h-9 w-9" : "h-10 w-10"
        }`}
      >
        <ProductImageDisplay
          image={summary.primaryProductImage}
          className="h-full w-full"
          emojiClassName={compact ? "text-base" : "text-lg"}
        />
      </div>
      <div className="min-w-0">
        <p
          className={`truncate font-medium text-zinc-900 ${compact ? "max-w-[160px] text-xs" : "max-w-[200px] text-sm"}`}
        >
          {summary.primaryProductName}
        </p>
        {showSource && (
          <div className="mt-1">
            <AdminOrderTypeBadge orderType={summary.orderType} />
          </div>
        )}
        {summary.additionalItemCount > 0 && (
          <p className="mt-0.5 text-xs font-medium text-zinc-500">
            +{summary.additionalItemCount} more item{summary.additionalItemCount === 1 ? "" : "s"}
          </p>
        )}
      </div>

      {order.items.length > 1 && (
        <div
          role="tooltip"
          className="pointer-events-none absolute left-0 top-full z-20 mt-2 hidden w-max max-w-xs rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-xs text-zinc-700 shadow-lg group-hover:block"
        >
          <p className="mb-1 font-semibold text-zinc-900">Order items</p>
          <ul className="space-y-1">
            {order.items.map((item) => (
              <li key={item.id}>
                {item.name}
                {item.quantity > 1 ? ` ×${item.quantity}` : ""}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
