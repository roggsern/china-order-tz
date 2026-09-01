import { formatPrice } from "@/lib/catalog/utils";
import {
  BULK_SHIPPING_NOTE,
  MIXED_VARIANTS_BULK_NOTE,
  nextTierUnlockMessage,
  volumePricingUnlocked,
  type VolumePricing,
} from "@/lib/pricing/volume-pricing";

interface BulkPricingPanelProps {
  pricing: VolumePricing | null;
  showVariantAggregationNote?: boolean;
  showShippingNote?: boolean;
  className?: string;
}

function tierLabel(tier: VolumePricing["tiers"][number]): string {
  if (tier.type === "percent_off" && tier.discount_percent) {
    return `${parseFloat(tier.discount_percent)}% off`;
  }
  return `${formatPrice(parseFloat(tier.unit_price))} each`;
}

export function BulkPricingPanel({
  pricing,
  showVariantAggregationNote = false,
  showShippingNote = false,
  className = "",
}: BulkPricingPanelProps) {
  if (!pricing || pricing.tiers.length === 0) return null;

  const unlocked = volumePricingUnlocked(pricing);
  const nextMessage = nextTierUnlockMessage(pricing, formatPrice);

  return (
    <section
      className={`rounded-2xl border border-zinc-200 bg-zinc-50/80 px-4 py-3.5 ${className}`}
      aria-label="Bulk pricing"
    >
      <p className="text-sm font-bold text-zinc-900">Buy More, Save More</p>
      <ul className="mt-2 space-y-1">
        {pricing.tiers.map((tier) => {
          const active = pricing.current_tier?.min_quantity === tier.min_quantity;
          return (
            <li
              key={`${tier.scope}-${tier.min_quantity}`}
              className={`flex items-baseline justify-between gap-3 text-sm ${
                active ? "font-semibold text-emerald-800" : "text-zinc-700"
              }`}
            >
              <span>{tier.min_quantity}+ pcs</span>
              <span className="tabular-nums">{tierLabel(tier)}</span>
            </li>
          );
        })}
      </ul>

      {unlocked ? (
        <p className="mt-3 text-sm font-semibold text-emerald-800" role="status">
          Bulk price unlocked
          {parseFloat(pricing.savings_total) > 0
            ? ` — you save ${formatPrice(parseFloat(pricing.savings_total))}`
            : ""}
        </p>
      ) : null}

      {nextMessage ? (
        <p className="mt-2 text-sm font-medium text-amber-800" role="status">
          {nextMessage}
        </p>
      ) : null}

      {showVariantAggregationNote ? (
        <p className="mt-2 text-xs text-zinc-500">{MIXED_VARIANTS_BULK_NOTE}</p>
      ) : null}

      {showShippingNote ? (
        <p className="mt-2 text-xs text-zinc-500">{BULK_SHIPPING_NOTE}</p>
      ) : null}
    </section>
  );
}
