import type { ProductOrigin, ProductShippingContext } from "@/lib/types/catalog";
import { formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import {
  resolveProductShippingOptions,
  type ResolvedShippingOption,
} from "@/lib/shipping/smart-engine";

export type DeliveryOption = {
  icon: string;
  label: string;
  detail: string;
  subdetail?: string;
};

export type CardShippingOption = {
  icon: string;
  label: string;
  shippingCost: string;
  deliveryDays: string;
};

export type ProductShippingOption = {
  icon: string;
  label: string;
  name: string;
  /** Null for local products — shown as negotiated delivery. */
  shippingCost: number | null;
  deliveryDays: string;
};

/** Coerce legacy numeric values to string; pass through flexible admin text as-is. */
export function normalizeDeliveryDays(value: unknown): string | undefined {
  if (value == null) return undefined;
  if (typeof value === "number") {
    return Number.isFinite(value) ? String(value) : undefined;
  }
  if (typeof value === "string") {
    const trimmed = value.trim();
    return trimmed || undefined;
  }
  return undefined;
}

/** @deprecated Use formatDays from @/lib/catalog/utils */
export { formatDays as formatDeliveryDays, formatDays } from "@/lib/catalog/utils";

function toProductShippingOption(option: ResolvedShippingOption): ProductShippingOption {
  return {
    icon: option.icon,
    label: option.label,
    name: option.name,
    shippingCost: option.unitCost,
    deliveryDays: option.deliveryDays,
  };
}

export function getProductShippingOptions(input: ProductShippingContext): ProductShippingOption[] {
  return resolveProductShippingOptions(input).map(toProductShippingOption);
}

export function getDeliveryOptions(origin: ProductOrigin): DeliveryOption[] {
  if (origin === "tz") {
    return [
      { icon: "🚚", label: "Local Delivery", detail: "Dar es Salaam", subdetail: "1–2 Days" },
      { icon: "📍", label: "Other Regions", detail: "Nationwide", subdetail: "2–5 Days" },
    ];
  }

  return [
    { icon: "✈", label: "Air Freight", detail: "Estimated Delivery", subdetail: "7–12 Days" },
    { icon: "🚢", label: "Sea Freight", detail: "Estimated Delivery", subdetail: "35–45 Days" },
  ];
}

export function getOriginLabel(origin: ProductOrigin): { flag: string; label: string } {
  if (origin === "tz") {
    return { flag: "🇹🇿", label: "Buy From TZ" };
  }
  return { flag: "🇨🇳", label: "Imported from China" };
}

function formatShippingCostDisplay(shippingCost: number | null): string {
  if (shippingCost === null) {
    return LOCAL_DELIVERY_NEGOTIATED_LABEL;
  }
  return formatPrice(shippingCost);
}

export function getProductCardShippingOptions(input: ProductShippingContext): {
  origin: { flag: string; label: string };
  options: CardShippingOption[];
} {
  const shippingOptions = getProductShippingOptions(input);

  return {
    origin: getOriginLabel(input.origin),
    options: shippingOptions.map((option) => ({
      icon: option.icon,
      label:
        input.origin === "tz"
          ? "Delivery"
          : option.label === "Air Freight"
            ? "AIR"
            : "SEA",
      shippingCost: formatShippingCostDisplay(option.shippingCost),
      deliveryDays: option.deliveryDays,
    })),
  };
}
