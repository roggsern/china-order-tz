"use client";

import type { CartConfigurationAttribute, CartLineItem } from "@/lib/types/cart";
import type { OrderConfigurationAttribute, OrderLineItem } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { getOriginLabel } from "@/lib/catalog/delivery";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { VariantLabel } from "@/components/catalog/VariantLabel";
import {
  WholesalePricingBadge,
  YouSavedMessage,
} from "@/components/catalog/WholesalePricingMessages";
import { CheckoutShippingSummary } from "./CheckoutShippingSummary";

type ConfigAttr = CartConfigurationAttribute | OrderConfigurationAttribute;

function resolveAttributes(item: CartLineItem | OrderLineItem): ConfigAttr[] {
  if (item.selectedAttributes && item.selectedAttributes.length > 0) {
    return item.selectedAttributes;
  }

  const legacy: ConfigAttr[] = [];
  if (item.variant?.color) {
    legacy.push({ name: "Color", value: item.variant.color });
  }
  if (item.variant?.storage) {
    legacy.push({ name: "Storage", value: item.variant.storage });
  }
  if (item.variant?.size || ("selectedSize" in item && item.selectedSize)) {
    const size =
      item.variant?.size ||
      ("selectedSize" in item ? item.selectedSize : null) ||
      "";
    if (size) legacy.push({ name: "Size", value: size });
  }
  if (legacy.length > 0) return legacy;

  if ("configurationLabel" in item && item.configurationLabel?.trim()) {
    return [{ name: "Configuration", value: item.configurationLabel.trim() }];
  }

  return [];
}

function getUnitPrice(item: CartLineItem | OrderLineItem): number {
  return item.unitPrice;
}

function getShippingMethod(item: CartLineItem | OrderLineItem) {
  if ("shipping" in item && item.shipping?.method) {
    return item.shipping.method;
  }
  return item.shippingMethod;
}

function getShippingDays(item: CartLineItem | OrderLineItem): string {
  if ("shipping" in item && item.shipping?.days) {
    return item.shipping.days;
  }
  return item.estimatedDeliveryDays;
}

function lineSavings(item: CartLineItem | OrderLineItem): number {
  const unit = getUnitPrice(item);
  const compare = item.compareAtUnitPrice;
  if (!(compare && compare > unit)) return 0;
  return (compare - unit) * item.quantity;
}

interface CheckoutLineItemCardProps {
  item: CartLineItem | OrderLineItem;
  /** When false, omit per-line shipping block (summary section shows shipping once). */
  showShipping?: boolean;
  compact?: boolean;
}

export function CheckoutLineItemCard({
  item,
  showShipping = true,
  compact = false,
}: CheckoutLineItemCardProps) {
  const unitPrice = getUnitPrice(item);
  const shippingMethod = getShippingMethod(item);
  const shippingDays = getShippingDays(item);
  const originCode =
    item.origin ?? (shippingMethod === "local_delivery" ? "tz" : "china");
  const origin = getOriginLabel(originCode);
  const attributes = resolveAttributes(item);
  const sku = item.configurationSku?.trim() ?? "";
  const savings = lineSavings(item);
  const wholesaleActive = savings > 0;
  const lineTotal = unitPrice * item.quantity;

  return (
    <li className={`py-4 first:pt-0 last:pb-0 ${compact ? "py-3" : ""}`}>
      <div className="flex gap-3">
        <div className="shrink-0 overflow-hidden rounded-xl border border-zinc-100 bg-white shadow-sm transition duration-200 hover:shadow-md">
          <ProductImageDisplay
            image={item.image}
            fallbackEmoji={item.image.emoji}
            fallbackGradient={item.image.gradient}
            className={compact ? "h-14 w-14" : "h-16 w-16"}
            emojiClassName="text-2xl"
          />
        </div>

        <div className="min-w-0 flex-1">
          <p className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900">
            {item.name}
          </p>

          {attributes.length > 0 ? (
            <div className="mt-2 flex flex-wrap gap-1.5">
              {attributes.map((attribute) => (
                <span
                  key={`${attribute.name}-${attribute.value}`}
                  className="inline-flex items-center gap-1 rounded-full border border-[#c9a227]/25 bg-[#c9a227]/8 px-2.5 py-1 text-[11px] font-semibold text-[#8b6914]"
                >
                  <span className="text-[#8b6914]/70">{attribute.name}:</span>
                  <span className="text-zinc-800">{attribute.value}</span>
                </span>
              ))}
            </div>
          ) : (
            <VariantLabel variant={item.variant} className="mt-1" />
          )}

          {sku ? (
            <p className="mt-1.5 text-[11px] font-medium tracking-wide text-zinc-400">
              SKU <span className="text-zinc-600">{sku}</span>
            </p>
          ) : null}

          <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
            <span>
              {origin.flag} {origin.label}
            </span>
            <span className="font-medium text-zinc-700">Qty {item.quantity}</span>
            <span className="tabular-nums">
              {formatPrice(unitPrice)}
              <span className="text-zinc-400"> / unit</span>
            </span>
          </div>

          {wholesaleActive ? (
            <div className="mt-2 space-y-1">
              <WholesalePricingBadge compact />
              <YouSavedMessage amount={savings} />
            </div>
          ) : null}
        </div>

        <div className="shrink-0 text-right">
          {wholesaleActive && item.compareAtUnitPrice ? (
            <p className="text-xs tabular-nums text-zinc-400 line-through">
              {formatPrice(item.compareAtUnitPrice * item.quantity)}
            </p>
          ) : null}
          <p className="text-sm font-semibold tabular-nums text-zinc-900">
            {formatPrice(lineTotal)}
          </p>
        </div>
      </div>

      {showShipping ? (
        <CheckoutShippingSummary
          method={shippingMethod}
          estimatedDelivery={shippingDays}
          compact
          className="mt-3"
        />
      ) : null}
    </li>
  );
}
