import type { ItemShippingBreakdown } from "@/lib/types/order";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { formatPrice } from "@/lib/catalog/utils";
import { getShippingMethodLabel } from "@/lib/shipping/smart-engine";

interface ShippingQuantityBreakdownProps {
  method: ShippingMethodCode;
  methodLabel?: string;
  unitCost: number;
  quantity: number;
  totalCost: number;
  className?: string;
  compact?: boolean;
}

export function ShippingQuantityBreakdown({
  method,
  methodLabel,
  unitCost,
  quantity,
  totalCost,
  className = "",
  compact = false,
}: ShippingQuantityBreakdownProps) {
  const label = methodLabel ?? getShippingMethodLabel(method);
  const qty = Math.max(1, quantity);

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
