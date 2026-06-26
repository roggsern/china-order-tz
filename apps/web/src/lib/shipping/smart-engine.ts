import { normalizeDeliveryDays } from "@/lib/catalog/delivery";
import {
  getDefaultFlatShippingDeliveryDays,
  getDefaultFlatShippingUnitCost,
} from "@/lib/shipping/config";
import type {
  ProductOrigin,
  ProductShippingContext,
  ProductShippingOptionConfig,
} from "@/lib/types/catalog";
import type { CartLineItem } from "@/lib/types/cart";
import type { ItemShippingBreakdown, OrderLineItem } from "@/lib/types/order";
import type { OrderSummaryBreakdown, ShippingMethodCode } from "@/lib/shipping/types";
import { originToFulfillmentSource } from "@/lib/shipping/types";

export type ResolvedShippingOption = {
  type: ProductShippingOptionConfig["type"];
  methodCode: ShippingMethodCode;
  label: string;
  name: string;
  icon: string;
  unitCost: number;
  deliveryDays: string;
};

const METHOD_META: Record<
  ShippingMethodCode,
  { label: string; name: string; icon: string; type: ProductShippingOptionConfig["type"] }
> = {
  air_freight: { label: "Air Freight", name: "Air Freight", icon: "✈", type: "air" },
  sea_freight: { label: "Sea Freight", name: "Sea Freight", icon: "🚢", type: "sea" },
  local_delivery: { label: "Local Delivery", name: "Local Delivery", icon: "🚚", type: "local" },
};

const TYPE_TO_METHOD: Record<ProductShippingOptionConfig["type"], ShippingMethodCode> = {
  air: "air_freight",
  sea: "sea_freight",
  local: "local_delivery",
};

function rawDeliveryDays(value: unknown): string {
  return normalizeDeliveryDays(value) ?? "—";
}

function positivePrice(value: unknown): number | null {
  const price = typeof value === "number" ? value : Number(value);
  if (!Number.isFinite(price) || price <= 0) return null;
  return price;
}

function isMethodEligibleForOrigin(method: ShippingMethodCode, origin: ProductOrigin): boolean {
  if (origin === "tz") return method === "local_delivery";
  return method === "air_freight" || method === "sea_freight";
}

function resolveEligibleMethod(
  origin: ProductOrigin,
  method: ShippingMethodCode,
): ShippingMethodCode {
  if (isMethodEligibleForOrigin(method, origin)) {
    return method;
  }
  return origin === "tz" ? "local_delivery" : "sea_freight";
}

function buildFlatFallbackOptions(
  input: ProductShippingContext,
): ResolvedShippingOption[] {
  const methodCodes: ShippingMethodCode[] =
    input.origin === "tz" ? ["local_delivery"] : ["air_freight", "sea_freight"];

  return methodCodes
    .map((methodCode) => {
      const unitCost = getDefaultFlatShippingUnitCost(methodCode);
      if (unitCost <= 0) return null;

      const meta = METHOD_META[methodCode];
      return {
        type: meta.type,
        methodCode,
        label: meta.label,
        name: meta.name,
        icon: meta.icon,
        unitCost,
        deliveryDays: getDefaultFlatShippingDeliveryDays(methodCode),
      } satisfies ResolvedShippingOption;
    })
    .filter(Boolean) as ResolvedShippingOption[];
}

/** Normalize admin/product shippingOptions; falls back to legacy airCost/seaCost, then flat rates. */
export function resolveProductShippingOptions(
  input: ProductShippingContext & { shippingOptions?: ProductShippingOptionConfig[] },
): ResolvedShippingOption[] {
  const configured = Array.isArray(input.shippingOptions)
    ? input.shippingOptions
        .map((option) => {
          const unitCost = positivePrice(option.price);
          if (!unitCost) return null;

          const methodCode = TYPE_TO_METHOD[option.type];
          const meta = METHOD_META[methodCode];

          return {
            type: option.type,
            methodCode,
            label: meta.label,
            name: meta.name,
            icon: meta.icon,
            unitCost,
            deliveryDays: rawDeliveryDays(option.deliveryDays),
          } satisfies ResolvedShippingOption;
        })
        .filter(Boolean) as ResolvedShippingOption[]
    : [];

  if (configured.length > 0) {
    return configured;
  }

  if (input.origin === "tz") {
    const localCost = positivePrice(input.airCost);
    if (localCost) {
      const meta = METHOD_META.local_delivery;
      return [
        {
          type: "local",
          methodCode: "local_delivery",
          label: meta.label,
          name: meta.name,
          icon: meta.icon,
          unitCost: localCost,
          deliveryDays: rawDeliveryDays(input.airDeliveryDays),
        },
      ];
    }

    return buildFlatFallbackOptions(input);
  }

  const options: ResolvedShippingOption[] = [];

  const airCost = positivePrice(input.airCost);
  if (airCost) {
    const meta = METHOD_META.air_freight;
    options.push({
      type: "air",
      methodCode: "air_freight",
      label: meta.label,
      name: meta.name,
      icon: meta.icon,
      unitCost: airCost,
      deliveryDays: rawDeliveryDays(input.airDeliveryDays),
    });
  }

  const seaCost = positivePrice(input.seaCost);
  if (seaCost) {
    const meta = METHOD_META.sea_freight;
    options.push({
      type: "sea",
      methodCode: "sea_freight",
      label: meta.label,
      name: meta.name,
      icon: meta.icon,
      unitCost: seaCost,
      deliveryDays: rawDeliveryDays(input.seaDeliveryDays),
    });
  }

  if (options.length > 0) {
    return options;
  }

  return buildFlatFallbackOptions(input);
}

function resolveBaseShippingPrice(
  item: ShippingItemInput,
  method: ShippingMethodCode,
): { unitCost: number; deliveryDays: string } {
  const options = resolveProductShippingOptions(item);
  const eligibleMethod = resolveEligibleMethod(item.origin, method);

  const selected = options.find((option) => option.methodCode === eligibleMethod);
  if (selected) {
    return {
      unitCost: selected.unitCost,
      deliveryDays: selected.deliveryDays,
    };
  }

  return {
    unitCost: getDefaultFlatShippingUnitCost(eligibleMethod),
    deliveryDays: getDefaultFlatShippingDeliveryDays(eligibleMethod),
  };
}

/** Core rule: line shipping = unit cost × quantity. */
export function calculateQuantityShipping(unitCost: number, quantity: number): number {
  const qty = Math.max(1, quantity);
  return unitCost * qty;
}

export function deriveUnitShippingCost(totalCost: number, quantity: number): number {
  const qty = Math.max(1, quantity);
  if (totalCost <= 0) return 0;
  return totalCost / qty;
}

export type ShippingItemInput = Pick<
  CartLineItem,
  | "origin"
  | "weightKg"
  | "categorySlug"
  | "airCost"
  | "seaCost"
  | "airDeliveryDays"
  | "seaDeliveryDays"
  | "shippingOptions"
>;

export type LineShippingInput = ShippingItemInput &
  Pick<
    CartLineItem,
    "quantity" | "shippingMethod" | "unitShippingCost" | "shippingCost"
  >;

/**
 * Single source of truth for shipping cost.
 * Rule: shippingTotal = baseShippingPrice × quantity
 */
export function getShippingTotal(
  item: ShippingItemInput,
  quantity: number,
  method: ShippingMethodCode,
): number {
  const { unitCost } = resolveBaseShippingPrice(item, method);
  return calculateQuantityShipping(unitCost, quantity);
}

export function getShippingQuote(
  item: ShippingItemInput,
  quantity: number,
  method: ShippingMethodCode,
): {
  shippingMethod: ShippingMethodCode;
  unitShippingCost: number;
  shippingTotal: number;
  estimatedDeliveryDays: string;
} {
  const qty = Math.max(1, quantity);
  const shippingMethod = resolveEligibleMethod(item.origin, method);
  const { unitCost, deliveryDays } = resolveBaseShippingPrice(item, shippingMethod);

  return {
    shippingMethod,
    unitShippingCost: unitCost,
    shippingTotal: calculateQuantityShipping(unitCost, qty),
    estimatedDeliveryDays: deliveryDays,
  };
}

export function resolveLineItemShipping(item: LineShippingInput): {
  shippingMethod: ShippingMethodCode;
  unitShippingCost: number;
  shippingCost: number;
  estimatedDeliveryDays: string;
} {
  const quantity = Math.max(1, item.quantity ?? 1);
  const options = resolveProductShippingOptions(item);

  const preferredMethod =
    item.shippingMethod &&
    (options.some((option) => option.methodCode === item.shippingMethod) ||
      options.length === 0)
      ? item.shippingMethod
      : getDefaultShippingMethod(item);

  const quote = getShippingQuote(item, quantity, preferredMethod);

  return {
    shippingMethod: quote.shippingMethod,
    unitShippingCost: quote.unitShippingCost,
    shippingCost: quote.shippingTotal,
    estimatedDeliveryDays: quote.estimatedDeliveryDays,
  };
}

export function getDefaultShippingMethod(item: LineShippingInput): ShippingMethodCode {
  const options = resolveProductShippingOptions(item);

  if (item.origin === "tz") {
    return "local_delivery";
  }

  if (options.length === 0) {
    return "sea_freight";
  }

  const quantity = Math.max(1, item.quantity ?? 1);
  let cheapest = options[0]!;
  let cheapestTotal = calculateQuantityShipping(cheapest.unitCost, quantity);

  for (const option of options.slice(1)) {
    const total = calculateQuantityShipping(option.unitCost, quantity);
    if (total < cheapestTotal) {
      cheapest = option;
      cheapestTotal = total;
    }
  }

  return cheapest.methodCode;
}

export function applyLineItemShipping<T extends CartLineItem>(item: T): T {
  const shipping = resolveLineItemShipping(item);
  return {
    ...item,
    ...shipping,
  };
}

export function syncCartLineItems(items: CartLineItem[]): CartLineItem[] {
  return items.map((item) => applyLineItemShipping(item));
}

export function calculateOrderSummary(
  items: Pick<CartLineItem, "unitPrice" | "quantity" | "shippingCost">[],
  discount = 0,
): OrderSummaryBreakdown {
  const productTotal = items.reduce(
    (sum, item) => sum + item.unitPrice * item.quantity,
    0,
  );
  const shippingTotal = items.reduce((sum, item) => sum + item.shippingCost, 0);
  const grandTotal = Math.max(0, productTotal + shippingTotal - discount);

  return {
    productTotal,
    shippingTotal,
    discount,
    grandTotal,
  };
}

export function getShippingMethodLabel(method: ShippingMethodCode): string {
  return METHOD_META[method]?.name ?? method;
}

export function getShippingMethodShortLabel(method: ShippingMethodCode): string {
  if (method === "air_freight") return "Air";
  if (method === "sea_freight") return "Sea";
  if (method === "local_delivery") return "Local";
  return getShippingMethodLabel(method);
}

export function buildItemShippingBreakdownFromCartItem(
  item: CartLineItem,
): ItemShippingBreakdown {
  const quantity = Math.max(1, item.quantity);
  const unitCost =
    item.unitShippingCost != null && item.unitShippingCost > 0
      ? item.unitShippingCost
      : deriveUnitShippingCost(item.shippingCost, quantity);

  return {
    itemId: item.id,
    productId: item.productId,
    productName: item.name,
    method: item.shippingMethod,
    methodLabel: getShippingMethodLabel(item.shippingMethod),
    unitCost,
    quantity,
    totalCost: item.shippingCost,
  };
}

export function buildItemShippingBreakdownFromOrderItem(
  item: OrderLineItem,
): ItemShippingBreakdown {
  const quantity = Math.max(1, item.quantity);
  const unitCost =
    item.shipping.unitCost > 0
      ? item.shipping.unitCost
      : deriveUnitShippingCost(item.shipping.cost, quantity);

  return {
    itemId: item.id,
    productId: item.productId,
    productName: item.name,
    method: item.shipping.method,
    methodLabel: getShippingMethodLabel(item.shipping.method),
    unitCost,
    quantity,
    totalCost: item.shipping.cost,
  };
}

export function buildOrderShippingBreakdown(
  items: OrderLineItem[],
): ItemShippingBreakdown[] {
  return items.map(buildItemShippingBreakdownFromOrderItem);
}

export function resolvePrimaryShippingMethod(
  items: Pick<OrderLineItem, "shippingMethod">[],
): ShippingMethodCode | null {
  const unique = [...new Set(items.map((item) => item.shippingMethod))];
  return unique.length === 1 ? unique[0]! : unique[0] ?? null;
}

/** Sum frozen per-line shipping costs — never recalculates from catalog. */
export function reconcileOrderShipping(orderItems: OrderLineItem[]): {
  shippingTotal: number;
  shippingMethod: ShippingMethodCode | null;
  itemShippingBreakdown: ItemShippingBreakdown[];
} {
  const itemShippingBreakdown = buildOrderShippingBreakdown(orderItems);
  const shippingTotal = itemShippingBreakdown.reduce((sum, row) => sum + row.totalCost, 0);

  return {
    shippingTotal,
    shippingMethod: resolvePrimaryShippingMethod(orderItems),
    itemShippingBreakdown,
  };
}

export function originFromMethod(method: ShippingMethodCode): ProductOrigin {
  return method === "local_delivery" ? "tz" : "china";
}

export function fulfillmentSourceFromOrigin(origin: ProductOrigin) {
  return originToFulfillmentSource(origin);
}
