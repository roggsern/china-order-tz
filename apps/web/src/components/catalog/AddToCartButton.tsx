"use client";

import { useState } from "react";
import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import {
  canAddProductToCart,
  hasSizeVariants,
  isSizeSelectionRequired,
} from "@/lib/catalog/variants";
import { CartIcon } from "@/components/home/icons";
import { useAddToCart } from "@/components/cart/CartProvider";

interface AddToCartButtonProps {
  product: Product;
  quantity?: number;
  disabled?: boolean;
  variant?: "card" | "detail";
  className?: string;
  selectedVariant?: ProductVariantChoice;
}

export function AddToCartButton({
  product,
  quantity = 1,
  disabled = false,
  variant = "card",
  className = "",
  selectedVariant,
}: AddToCartButtonProps) {
  const needsSize = isSizeSelectionRequired(product);
  const sizeMissing = needsSize && !canAddProductToCart(product, selectedVariant);
  const isDisabled = disabled || sizeMissing;

  const addToCart = useAddToCart(product, quantity, {
    variant: selectedVariant,
    disabled: isDisabled,
  });
  const [added, setAdded] = useState(false);

  const handleClick = () => {
    if (isDisabled) return;

    addToCart();
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1800);
  };

  const baseClasses =
    variant === "card"
      ? "inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 py-2.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-zinc-900 disabled:hover:text-white"
      : "inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 py-3.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-zinc-900 disabled:hover:text-white";

  const label = disabled
    ? "Out of Stock"
    : sizeMissing && hasSizeVariants(product)
      ? "Select Size"
      : added
        ? "Added!"
        : "Add to Cart";

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={isDisabled}
      className={`${baseClasses} ${added ? "bg-[#c9a227] text-zinc-900 hover:bg-[#c9a227] hover:text-zinc-900" : ""} ${variant === "detail" ? "flex-1" : ""} ${className}`}
    >
      <CartIcon className="h-4 w-4" />
      {label}
    </button>
  );
}
