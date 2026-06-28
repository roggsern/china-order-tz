"use client";

import { useMemo } from "react";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { formatDeliveryEstimate, getMethodByCode, getSelectableMethodsForOrigin } from "@/lib/shipping/engine";
import { getShippingTotal, type ShippingItemInput } from "@/lib/shipping/smart-engine";
import { formatDays, formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import {
  ShippingMethodCard,
  ShippingMethodCardGrid,
  ShippingMethodSectionLabel,
} from "@/components/shipping/ShippingMethodCard";

type ShippingMethodCardsProps = ShippingItemInput & {
  quantity: number;
  selectedMethod: ShippingMethodCode;
  onSelect: (methodCode: ShippingMethodCode) => void;
  compact?: boolean;
};

type MethodBadge = "Best Value" | "Fastest";

function resolveMethodBadges(
  methods: ReturnType<typeof getSelectableMethodsForOrigin>,
  item: ShippingItemInput,
  quantity: number,
): Partial<Record<ShippingMethodCode, MethodBadge>> {
  if (methods.length === 0) {
    return {};
  }

  const priced = methods.map((method) => ({
    code: method.code,
    cost: getShippingTotal(item, quantity, method.code),
    minDays: method.deliveryEstimate.minDays,
  }));

  const cheapest = priced.reduce((best, current) => (current.cost < best.cost ? current : best));
  const fastest = priced.reduce((best, current) =>
    current.minDays < best.minDays ? current : best,
  );

  const badges: Partial<Record<ShippingMethodCode, MethodBadge>> = {};
  badges[cheapest.code] = "Best Value";
  if (fastest.code !== cheapest.code) {
    badges[fastest.code] = "Fastest";
  } else if (methods.length > 1) {
    const alternate = methods.find((method) => method.code !== cheapest.code);
    if (alternate) {
      badges[alternate.code] = "Fastest";
    }
  }

  return badges;
}

export function ShippingMethodCards({
  origin,
  weightKg,
  categorySlug,
  airCost,
  seaCost,
  airDeliveryDays,
  seaDeliveryDays,
  shippingOptions,
  quantity,
  selectedMethod,
  onSelect,
  compact = false,
}: ShippingMethodCardsProps) {
  const methods = useMemo(() => getSelectableMethodsForOrigin(origin), [origin]);

  const item = useMemo<ShippingItemInput>(
    () => ({
      origin,
      weightKg,
      categorySlug,
      airCost,
      seaCost,
      airDeliveryDays,
      seaDeliveryDays,
      shippingOptions,
    }),
    [origin, weightKg, categorySlug, airCost, seaCost, airDeliveryDays, seaDeliveryDays, shippingOptions],
  );

  const badges = useMemo(
    () => resolveMethodBadges(methods, item, quantity),
    [methods, item, quantity],
  );

  const cards = useMemo(
    () =>
      methods.map((method) => {
        const isAir = method.code === "air_freight";
        const isSea = method.code === "sea_freight";
        const title = isAir ? "Air ✈" : isSea ? "Sea 🚢" : method.name;

        return {
          code: method.code,
          icon: method.icon,
          title,
          price: getShippingTotal(item, quantity, method.code),
          unitPrice: getShippingTotal(item, 1, method.code),
          deliveryLabel: `${formatDeliveryEstimate(method.code)} transit`,
          badge: badges[method.code],
        };
      }),
    [badges, item, methods, quantity],
  );

  if (methods.length === 0) {
    return null;
  }

  return (
    <div className={compact ? "mt-3 space-y-2" : "mt-5 space-y-3"}>
      <ShippingMethodSectionLabel compact={compact} />
      <ShippingMethodCardGrid compact={compact}>
        {cards.map((card) => (
          <ShippingMethodCard
            key={card.code}
            icon={card.icon}
            title={card.title}
            price={card.price}
            unitPrice={card.unitPrice}
            quantity={quantity}
            deliveryLabel={card.deliveryLabel}
            isSelected={selectedMethod === card.code}
            onSelect={() => onSelect(card.code)}
            badge={card.badge}
          />
        ))}
      </ShippingMethodCardGrid>
    </div>
  );
}

export function LocalDeliveryCard({
  shippingMethod,
  shippingCost,
  estimatedDeliveryDays,
  compact = false,
}: {
  shippingMethod: ShippingMethodCode;
  shippingCost: number | null;
  estimatedDeliveryDays: string | number;
  compact?: boolean;
}) {
  const method = getMethodByCode(shippingMethod);
  const deliveryLabel =
    typeof estimatedDeliveryDays === "number"
      ? `Est. ${estimatedDeliveryDays} day${estimatedDeliveryDays === 1 ? "" : "s"}`
      : formatDays(estimatedDeliveryDays);
  const priceLabel =
    shippingCost === null ? LOCAL_DELIVERY_NEGOTIATED_LABEL : `${formatPrice(shippingCost)} shipping`;

  return (
    <div
      className={`rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50 to-white ${
        compact ? "mt-3 p-3" : "mt-5 p-4"
      }`}
    >
      <div className="flex items-start gap-3">
        <span
          className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-lg"
          aria-hidden
        >
          {method?.icon ?? "🚚"}
        </span>
        <div className="min-w-0 flex-1">
          <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-zinc-900">
            Local Delivery 🚚
            <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
              Auto-assigned
            </span>
          </p>
          <p className="mt-1 text-xs text-zinc-600">{deliveryLabel}</p>
          <p className="mt-1 text-xs font-semibold text-[#8b6914]">{priceLabel}</p>
        </div>
      </div>
    </div>
  );
}
