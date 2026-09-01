import { formatPrice } from "@/lib/catalog/utils";
import { MIXED_VARIANTS_BULK_NOTE, parseVolumeMoney } from "@/lib/pricing/volume-pricing";
import { cartBulkHeadline, resolveCartGroupBulkPresentation } from "@/lib/cart/cart-product-bulk-banner";
import type { CartLineItem } from "@/lib/types/cart";

interface CartProductBulkBannerProps {
  items: CartLineItem[];
}

export function CartProductBulkBanner({ items }: CartProductBulkBannerProps) {
  const presentation = resolveCartGroupBulkPresentation(items, formatPrice);
  if (!presentation) return null;

  const { pricing, mixedVariants, mixedUnitPrices, unlocked, nextMessage, savingsTotal } =
    presentation;

  return (
    <div
      className="rounded-2xl border border-zinc-200 bg-zinc-50/90 px-4 py-3"
      role="status"
      aria-label="Bulk pricing"
    >
      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
        Bulk Pricing
      </p>
      <p className="mt-1 text-sm font-semibold text-zinc-900">{cartBulkHeadline(pricing)}</p>
      {unlocked && !mixedUnitPrices && parseVolumeMoney(pricing.resolved_unit_price) > 0 ? (
        <p className="mt-0.5 text-sm text-emerald-800">
          {formatPrice(parseVolumeMoney(pricing.resolved_unit_price))} each
        </p>
      ) : null}
      {unlocked && mixedUnitPrices ? (
        <p className="mt-0.5 text-sm text-zinc-600">
          Unit prices may differ by variant — see each line.
        </p>
      ) : null}
      {unlocked && savingsTotal > 0.001 ? (
        <p className="mt-1 text-sm font-medium text-emerald-800">
          You save {formatPrice(savingsTotal)} on this product
        </p>
      ) : null}
      {nextMessage ? (
        <p className="mt-1.5 text-sm font-medium text-amber-800">{nextMessage}</p>
      ) : null}
      {mixedVariants ? (
        <p className="mt-1.5 text-xs text-zinc-500">{MIXED_VARIANTS_BULK_NOTE}</p>
      ) : null}
    </div>
  );
}
