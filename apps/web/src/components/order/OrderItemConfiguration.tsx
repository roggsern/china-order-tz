"use client";

import type { OrderConfigurationAttribute, OrderLineItem } from "@/lib/types/order";
import { VariantLabel } from "@/components/catalog/VariantLabel";

function uniqueAttributes(
  attributes: OrderConfigurationAttribute[] | undefined,
): OrderConfigurationAttribute[] {
  if (!attributes?.length) {
    return [];
  }
  const seen = new Set<string>();
  return attributes.filter((attr) => {
    const key = `${attr.name}:${attr.value}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

interface OrderItemConfigurationProps {
  item: OrderLineItem;
  tone?: "light" | "dark";
  className?: string;
}

/** Renders variant + configuration chips for success/tracking item rows. */
export function OrderItemConfiguration({
  item,
  tone = "light",
  className = "",
}: OrderItemConfigurationProps) {
  const attributes = uniqueAttributes(item.selectedAttributes);
  const chipClass =
    tone === "dark"
      ? "rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-300 ring-1 ring-zinc-700"
      : "rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold text-zinc-600 ring-1 ring-zinc-200/80";

  return (
    <div className={className}>
      <VariantLabel variant={item.variant} />

      {item.configurationLabel ? (
        <p
          className={`mt-1 text-xs font-medium ${
            tone === "dark" ? "text-zinc-300" : "text-zinc-700"
          }`}
        >
          {item.configurationLabel}
        </p>
      ) : null}

      {attributes.length > 0 ? (
        <ul className="mt-1.5 flex flex-wrap gap-1.5" aria-label="Selected configuration">
          {attributes.map((attr) => (
            <li key={`${attr.name}-${attr.value}`} className={chipClass}>
              {attr.name}: {attr.value}
            </li>
          ))}
        </ul>
      ) : null}

      {item.configurationSku ? (
        <p
          className={`mt-1 font-mono text-[10px] ${
            tone === "dark" ? "text-zinc-500" : "text-zinc-400"
          }`}
        >
          SKU {item.configurationSku}
        </p>
      ) : null}

      {item.selectedSize && !item.configurationLabel && attributes.length === 0 ? (
        <p
          className={`mt-1 text-xs ${tone === "dark" ? "text-zinc-400" : "text-zinc-500"}`}
        >
          Size {item.selectedSize}
        </p>
      ) : null}
    </div>
  );
}
