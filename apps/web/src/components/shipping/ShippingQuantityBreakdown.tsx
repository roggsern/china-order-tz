import type { ItemShippingBreakdown } from "@/lib/types/order";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import { getShippingMethodLabel } from "@/lib/shipping/smart-engine";

interface ShippingQuantityBreakdownProps {
  method: ShippingMethodCode;
  methodLabel?: string;
  unitCost: number;
  quantity: number;
  totalCost: number;
  isNegotiated?: boolean;
  className?: string;
  compact?: boolean;
}

export function ShippingQuantityBreakdown({
  method,
  methodLabel,
  unitCost,
  quantity,
  totalCost,
  isNegotiated = false,
  className = "",
  compact = false,
}: ShippingQuantityBreakdownProps) {
  const label = methodLabel ?? getShippingMethodLabel(method);
  const qty = Math.max(1, quantity);

  if (isNegotiated || (totalCost <= 0 && method === "local_delivery")) {
    return (
      <p className={`text-xs font-medium text-[#8b6914] ${className}`}>
        {label}: {LOCAL_DELIVERY_NEGOTIATED_LABEL}
      </p>
    );
  }

  if (compact && qty <= 1) {
    return (
      <p className={`text-xs text-zinc-500 ${className}`}>
        {label}: {formatPrice(totalCost)}
      </p>
    );
  }

  return (
    <p className={`text-xs text-zinc-600 ${className}`}>
      <span className="font-medium text-zinc-800">Shipping ({label})</span>
      {": "}
      {formatPrice(unitCost)}
      {qty > 1 ? (
        <>
          {" "}
          × {qty} = <span className="font-semibold text-zinc-900">{formatPrice(totalCost)}</span>
        </>
      ) : (
        <> = {formatPrice(totalCost)}</>
      )}
    </p>
  );
}

interface ShippingBreakdownListProps {
  rows: ItemShippingBreakdown[];
  className?: string;
  compact?: boolean;
}

export function ShippingBreakdownList({
  rows,
  className = "",
  compact = false,
}: ShippingBreakdownListProps) {
  if (rows.length === 0) return null;

  return (
    <ul className={`space-y-1.5 ${className}`}>
      {rows.map((row) => (
        <li key={row.itemId}>
          {!compact && (
            <p className="text-[11px] font-medium text-zinc-500">{row.productName}</p>
          )}
          <ShippingQuantityBreakdown
            method={row.method}
            methodLabel={row.methodLabel}
            unitCost={row.unitCost}
            quantity={row.quantity}
            totalCost={row.totalCost}
            compact={compact}
          />
        </li>
      ))}
    </ul>
  );
}
