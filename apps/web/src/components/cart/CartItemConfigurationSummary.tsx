import type { CartConfigurationAttribute, CartLineItem } from "@/lib/types/cart";
import { VariantLabel } from "@/components/catalog/VariantLabel";

interface CartItemConfigurationSummaryProps {
  item: CartLineItem;
  className?: string;
}

function attributesFromItem(item: CartLineItem): CartConfigurationAttribute[] {
  if (item.selectedAttributes && item.selectedAttributes.length > 0) {
    return item.selectedAttributes;
  }

  const legacy: CartConfigurationAttribute[] = [];
  if (item.variant?.color) {
    legacy.push({ name: "Color", value: item.variant.color });
  }
  if (item.variant?.storage) {
    legacy.push({ name: "Storage", value: item.variant.storage });
  }
  if (item.variant?.size || item.selectedSize) {
    legacy.push({
      name: "Size",
      value: item.variant?.size || item.selectedSize || "",
    });
  }

  if (legacy.length > 0) return legacy;

  if (item.configurationLabel?.trim()) {
    return [{ name: "Configuration", value: item.configurationLabel.trim() }];
  }

  return [];
}

export function CartItemConfigurationSummary({
  item,
  className = "",
}: CartItemConfigurationSummaryProps) {
  const attributes = attributesFromItem(item);
  const sku = item.configurationSku?.trim();

  if (attributes.length === 0 && !sku) {
    return <VariantLabel variant={item.variant} className={className} />;
  }

  return (
    <div className={`space-y-2 ${className}`}>
      {attributes.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {attributes.map((attribute) => (
            <span
              key={`${attribute.name}-${attribute.value}`}
              className="inline-flex items-center gap-1 rounded-full border border-[#c9a227]/25 bg-[#c9a227]/8 px-2.5 py-1 text-[11px] font-semibold text-[#8b6914]"
            >
              <span className="text-[#8b6914]/70">{attribute.name}</span>
              <span className="text-zinc-800">{attribute.value}</span>
            </span>
          ))}
        </div>
      ) : null}

      {sku ? (
        <p className="text-[11px] font-medium tracking-wide text-zinc-400">
          SKU <span className="text-zinc-600">{sku}</span>
        </p>
      ) : null}
    </div>
  );
}
