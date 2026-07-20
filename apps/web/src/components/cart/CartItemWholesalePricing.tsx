import { formatPrice } from "@/lib/catalog/utils";
import {
  WholesalePricingBadge,
  YouSavedMessage,
} from "@/components/catalog/WholesalePricingMessages";

interface CartItemWholesalePricingProps {
  quantity: number;
  unitPrice: number;
  compareAtUnitPrice: number;
  shippingCost: number;
  includeShipping: boolean;
  className?: string;
}

/**
 * Explains why the line total got cheaper when a quantity tier is active.
 */
export function CartItemWholesalePricing({
  quantity,
  unitPrice,
  compareAtUnitPrice,
  shippingCost,
  includeShipping,
  className = "",
}: CartItemWholesalePricingProps) {
  const originalSubtotal = compareAtUnitPrice * quantity;
  const discountedSubtotal = unitPrice * quantity;
  const moqDiscount = originalSubtotal - discountedSubtotal;
  const itemTotal = discountedSubtotal + (includeShipping ? shippingCost : 0);

  if (moqDiscount <= 0) return null;

  return (
    <div
      className={`overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/40 ${className}`}
      role="status"
    >
      <div className="flex items-center gap-2 border-b border-emerald-100 bg-emerald-100/60 px-4 py-2.5">
        <WholesalePricingBadge />
      </div>

      <div className="flex flex-wrap items-end gap-x-2 gap-y-1 px-4 pt-3">
        <p className="text-xl font-extrabold tabular-nums text-red-600">
          {formatPrice(unitPrice)}
        </p>
        <p className="pb-0.5 text-sm font-medium text-zinc-400 line-through">
          {formatPrice(compareAtUnitPrice)}
        </p>
        <p className="pb-0.5 text-xs text-zinc-500">per unit</p>
      </div>

      <dl className="space-y-2 px-4 py-3 text-sm">
        <div className="flex items-center justify-between gap-3">
          <dt className="text-zinc-600">Original subtotal</dt>
          <dd className="font-medium tabular-nums text-zinc-500 line-through">
            {formatPrice(originalSubtotal)}
          </dd>
        </div>
        <div className="flex items-center justify-between gap-3">
          <dt className="font-medium text-emerald-800">MOQ discount</dt>
          <dd className="font-semibold tabular-nums text-emerald-700">
            −{formatPrice(moqDiscount)}
          </dd>
        </div>
        {includeShipping ? (
          <div className="flex items-center justify-between gap-3">
            <dt className="text-zinc-600">Shipping</dt>
            <dd className="font-medium tabular-nums text-zinc-900">
              {formatPrice(shippingCost)}
            </dd>
          </div>
        ) : null}
        <div className="flex items-center justify-between gap-3 border-t border-emerald-100 pt-2">
          <dt className="font-semibold text-zinc-800">Item total</dt>
          <dd className="text-base font-bold tabular-nums text-zinc-900">
            {formatPrice(itemTotal)}
          </dd>
        </div>
      </dl>

      <YouSavedMessage amount={moqDiscount} tone="banner" className="rounded-none" />
    </div>
  );
}
