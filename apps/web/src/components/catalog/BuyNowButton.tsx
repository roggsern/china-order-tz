"use client";

import { useRouter } from "next/navigation";
import type { Product } from "@/lib/types/catalog";
import { useCartActions } from "@/lib/cart/context";

interface BuyNowButtonProps {
  product: Product;
  quantity?: number;
  disabled?: boolean;
  className?: string;
  label?: string;
  configurationId?: string | null;
  configurationLabel?: string;
  configurationSku?: string;
  selectedAttributes?: Array<{ name: string; value: string; slug?: string | null }>;
  quotedUnitPrice?: number;
  compareAtUnitPrice?: number;
  stockOverride?: number;
}

export function BuyNowButton({
  product,
  quantity = 1,
  disabled = false,
  className = "",
  label = "Buy Now",
  configurationId = null,
  configurationLabel = "",
  configurationSku,
  selectedAttributes,
  quotedUnitPrice,
  compareAtUnitPrice,
  stockOverride,
}: BuyNowButtonProps) {
  const router = useRouter();
  const { addToCart } = useCartActions();

  const handleClick = () => {
    if (disabled) return;

    addToCart({
      product,
      quantity,
      configurationId,
      configurationLabel,
      configurationSku,
      selectedAttributes,
      quotedUnitPrice,
      compareAtUnitPrice,
      stockOverride,
    });

    router.push("/checkout");
  };

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={disabled}
      className={`inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] via-[#d4b83d] to-[#e8c547] py-3.5 text-sm font-bold tracking-wide text-zinc-900 shadow-[0_4px_16px_rgba(201,162,39,0.35)] transition-all duration-200 ease-out hover:from-[#b8921f] hover:via-[#c9a227] hover:to-[#e0c040] hover:shadow-[0_6px_22px_rgba(201,162,39,0.45)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:from-zinc-300 disabled:via-zinc-300 disabled:to-zinc-300 disabled:text-zinc-500 disabled:shadow-none ${className}`}
    >
      {label}
    </button>
  );
}
