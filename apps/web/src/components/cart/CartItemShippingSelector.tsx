"use client";

import { useMemo } from "react";
import { getCartItemShippingOptions, getShippingTotal } from "@/lib/cart/shipping";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import type { ShippingItemInput } from "@/lib/shipping/smart-engine";
import { formatDeliveryWindow } from "@/lib/catalog/utils";
import { useCartActions } from "@/lib/cart/context";
import { LocalDeliveryCard } from "@/components/shipping/ShippingMethodCards";
import {
  ShippingMethodCard,
  ShippingMethodCardGrid,
} from "@/components/shipping/ShippingMethodCard";

const SHIPPING_LABEL_TO_METHOD: Record<string, ShippingMethodCode> = {
  "Air Freight": "air_freight",
  "Sea Freight": "sea_freight",
  "Local Delivery": "local_delivery",
};

interface CartItemShippingSelectorProps extends ShippingItemInput {
  itemId: string;
  selectedMethod: ShippingMethodCode;
  quantity: number;
}

function CartItemShippingSelectorInner({
  itemId,
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
}: CartItemShippingSelectorProps) {
  const { updateShippingMethod } = useCartActions();

  const itemContext = useMemo<ShippingItemInput>(
    () => ({
      origin,
      weightKg,
      categorySlug: categorySlug ?? "",
      airCost,
      seaCost,
      airDeliveryDays,
      seaDeliveryDays,
      shippingOptions,
    }),
    [
      origin,
      weightKg,
      categorySlug,
      airCost,
      seaCost,
      airDeliveryDays,
      seaDeliveryDays,
      shippingOptions,
    ],
  );

  const options = useMemo(
    () => getCartItemShippingOptions(itemContext),
    [itemContext],
  );

  const lineQuantity = Math.max(1, quantity ?? 1);

  const pricedOptions = useMemo(
    () =>
      options
        .map((option) => {
          const methodCode = SHIPPING_LABEL_TO_METHOD[option.label];
          if (!methodCode) return null;

          const isAir = option.label === "Air Freight";
          const isSea = option.label === "Sea Freight";
          const title = isAir ? "Air Freight" : isSea ? "Sea Freight" : option.name;

          return {
            key: option.label,
            methodCode,
            icon: option.icon,
            title,
            price: getShippingTotal(itemContext, lineQuantity, methodCode),
            unitPrice: getShippingTotal(itemContext, 1, methodCode),
            deliveryLabel: `Estimated delivery: ${formatDeliveryWindow(option.deliveryDays)}`,
          };
        })
        .filter(Boolean) as Array<{
        key: string;
        methodCode: ShippingMethodCode;
        icon: string;
        title: string;
        price: number;
        unitPrice: number;
        deliveryLabel: string;
      }>,
    [itemContext, lineQuantity, options],
  );

  if (pricedOptions.length === 0) {
    return null;
  }

  return (
    <div className="mt-4 space-y-3">
      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
        Change shipping method
      </p>
      <ShippingMethodCardGrid>
        {pricedOptions.map((option) => (
          <ShippingMethodCard
            key={option.key}
            icon={option.icon}
            title={option.title}
            price={option.price}
            unitPrice={option.unitPrice}
            quantity={lineQuantity}
            deliveryLabel={option.deliveryLabel}
            isSelected={selectedMethod === option.methodCode}
            onSelect={() => updateShippingMethod(itemId, option.methodCode)}
          />
        ))}
      </ShippingMethodCardGrid>
    </div>
  );
}

export const CartItemShippingSelector = CartItemShippingSelectorInner;

interface LocalDeliveryBadgeProps {
  shippingMethod: ShippingMethodCode;
  shippingCost: number | null;
  estimatedDeliveryDays: string;
}

export function LocalDeliveryBadge({
  shippingMethod,
  shippingCost,
  estimatedDeliveryDays,
}: LocalDeliveryBadgeProps) {
  return (
    <LocalDeliveryCard
      shippingMethod={shippingMethod}
      shippingCost={shippingCost}
      estimatedDeliveryDays={estimatedDeliveryDays}
    />
  );
}
