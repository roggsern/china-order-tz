"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { Product } from "@/lib/types/catalog";
import { useCartActions } from "@/lib/cart/context";
import { runBuyNowUi } from "@/lib/cart/add-to-cart-ui";
import { showCustomerToast } from "@/lib/customer/customer-toast";
import {
  formatBuyNowInterceptMessage,
  type PurchaseQuantityPresentation,
} from "@/lib/purchasing/purchase-quantity";

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
  purchaseQuantity?: PurchaseQuantityPresentation | null;
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
  purchaseQuantity = null,
}: BuyNowButtonProps) {
  const router = useRouter();
  const { addToCart } = useCartActions();
  const [pending, setPending] = useState(false);
  const blockedMessage = formatBuyNowInterceptMessage(purchaseQuantity);
  const blocked = Boolean(blockedMessage);

  const handleClick = () => {
    if (disabled || pending) return;
    if (blocked && blockedMessage) {
      showCustomerToast({
        variant: "info",
        text: blockedMessage,
      });
      return;
    }

    setPending(true);
    void runBuyNowUi(
      () =>
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
        }),
      {
        onSuccess: () => {
          setPending(false);
          router.push("/checkout");
        },
        onFailure: (message) => {
          showCustomerToast(message);
          setPending(false);
        },
      },
    );
  };

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={disabled || pending || blocked}
      aria-busy={pending || undefined}
      title={blockedMessage ?? undefined}
      className={`inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] via-[#d4b83d] to-[#e8c547] py-3.5 text-sm font-bold tracking-wide text-zinc-900 shadow-[0_4px_16px_rgba(201,162,39,0.35)] transition-all duration-200 ease-out hover:from-[#b8921f] hover:via-[#c9a227] hover:to-[#e0c040] hover:shadow-[0_6px_22px_rgba(201,162,39,0.45)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:from-zinc-300 disabled:via-zinc-300 disabled:to-zinc-300 disabled:text-zinc-500 disabled:shadow-none ${className}`}
    >
      {pending ? "Adding…" : label}
    </button>
  );
}
