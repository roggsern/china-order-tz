import type { ProductVariantChoice } from "@/lib/types/catalog";
import { formatVariantLabel } from "@/lib/catalog/variants";

interface VariantLabelProps {
  variant?: ProductVariantChoice;
  className?: string;
}

export function VariantLabel({ variant, className = "" }: VariantLabelProps) {
  const lines = formatVariantLabel(variant);
  if (lines.length === 0) return null;

  return (
    <ul className={`space-y-0.5 ${className}`}>
      {lines.map((line) => (
        <li key={line} className="text-xs text-zinc-500">
          · {line}
        </li>
      ))}
    </ul>
  );
}
