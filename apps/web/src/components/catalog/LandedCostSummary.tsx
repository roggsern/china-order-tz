import { formatPrice } from "@/lib/catalog/utils";
import type { ProductShippingContext } from "@/lib/types/catalog";
import { calculateProductDetailLandedCost } from "@/lib/shipping/product-detail-cost";
import type { CartMoqHint } from "@/lib/cart/quote";

interface LandedCostSummaryProps {
  productPrice: number;
  /** Pre-MOQ unit price (base / configuration override). */
  compareAtUnitPrice?: number | null;
  shippingCost: number | null;
  quantity?: number;
  shippingContext?: ProductShippingContext;
  /** When false, totals are withheld until the customer finishes choosing options. */
  isReady?: boolean;
  /** Kept for compatibility — MOQ messaging lives near quantity on the PDP. */
  moqHint?: CartMoqHint | null;
  className?: string;
}

export function LandedCostSummary({
  productPrice,
  compareAtUnitPrice = null,
  shippingCost,
  quantity = 1,
  shippingContext,
  isReady = true,
  className = "",
}: LandedCostSummaryProps) {
  const compareAt =
    typeof compareAtUnitPrice === "number" && Number.isFinite(compareAtUnitPrice)
      ? compareAtUnitPrice
      : productPrice;
  const moqDiscountPerUnit = Math.max(0, compareAt - productPrice);
  const moqDiscount = moqDiscountPerUnit * Math.max(1, quantity);
  const wholesaleApplied = isReady && moqDiscount > 0.001;

  const { productSubtotal, serviceFee, estimatedTotal } = calculateProductDetailLandedCost({
    productPrice,
    quantity,
    shippingCost,
    context: shippingContext,
  });
  const hasShipping = isReady && shippingCost !== null;

  return (
    <div
      className={`rounded-2xl border border-[#c9a227]/20 bg-gradient-to-br from-[#c9a227]/5 to-white p-5 shadow-[0_4px_20px_rgba(201,162,39,0.08)] ${className}`}
      aria-label="Order summary"
    >
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.15em] text-[#8b6914]">
            Order Summary
          </p>
          <p className="mt-1 text-xs text-zinc-500">
            {!isReady
              ? "Finish choosing your options to see an accurate total."
              : wholesaleApplied
                ? "Your total reflects the active wholesale tier."
                : "Your running total with the selected shipping method."}
          </p>
        </div>
        {wholesaleApplied ? (
          <span
            className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800 ring-1 ring-emerald-200/80"
            role="status"
          >
            <span aria-hidden>✓</span>
            Wholesale Tier Applied
          </span>
        ) : null}
      </div>

      <dl className="mt-4 space-y-2.5">
        <div className="flex items-center justify-between text-sm">
          <dt className="text-zinc-600">Products</dt>
          <dd className="font-semibold tabular-nums text-zinc-900">
            {isReady ? formatPrice(productSubtotal) : "—"}
          </dd>
        </div>

        {wholesaleApplied ? (
          <div className="flex items-center justify-between text-sm">
            <dt className="font-medium text-emerald-800">Wholesale Saving</dt>
            <dd className="font-semibold tabular-nums text-emerald-700">
              −{formatPrice(moqDiscount)}
            </dd>
          </div>
        ) : null}

        <div className="flex items-center justify-between text-sm">
          <dt className="text-zinc-600">Shipping</dt>
          <dd className="font-semibold text-zinc-900">
            {hasShipping ? formatPrice(shippingCost ?? 0) : "—"}
          </dd>
        </div>

        {isReady && serviceFee > 0 && (
          <div className="flex items-center justify-between text-sm">
            <dt className="text-zinc-600">Service Fee</dt>
            <dd className="font-semibold text-zinc-900">{formatPrice(serviceFee)}</dd>
          </div>
        )}

        <div className="border-t border-[#c9a227]/15 pt-2.5">
          <div className="flex items-center justify-between">
            <dt className="text-sm font-semibold text-zinc-800">Estimated Total</dt>
            <dd className="text-lg font-extrabold tracking-tight text-zinc-900">
              {isReady ? formatPrice(estimatedTotal) : "—"}
            </dd>
          </div>
        </div>
      </dl>

      {wholesaleApplied ? (
        <p className="mt-3 text-center text-sm font-bold text-emerald-700">
          You save {formatPrice(moqDiscount)} with wholesale pricing
        </p>
      ) : null}
    </div>
  );
}
