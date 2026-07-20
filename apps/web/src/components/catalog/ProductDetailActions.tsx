"use client";

/**
 * Legacy product-detail actions (hardcoded size/color/storage).
 * The live PDP uses ProductDetailPurchasePanel / ProductDetailMobile with
 * ProductConfigurationPicker instead. Kept only for non-PDP references.
 */
import { useState } from "react";
import type { Product } from "@/lib/types/catalog";
import { QuantitySelector } from "./QuantitySelector";
import { AddToCartButton } from "./AddToCartButton";
import { BuyNowButton } from "./BuyNowButton";
import { WishlistButton } from "./WishlistButton";

interface ProductDetailActionsProps {
  product: Product;
}

export function ProductDetailActions({ product }: ProductDetailActionsProps) {
  const [quantity, setQuantity] = useState(1);
  const isOutOfStock = product.stock <= 0;

  return (
    <div className="mt-8 space-y-5 border-t border-zinc-100 pt-8">
      <QuantitySelector
        quantity={quantity}
        onChange={setQuantity}
        max={Math.min(product.stock, 99)}
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <AddToCartButton
          product={product}
          quantity={quantity}
          variant="detail"
          disabled={isOutOfStock}
        />
        <BuyNowButton product={product} quantity={quantity} disabled={isOutOfStock} />
        <WishlistButton
          productId={product.id}
          slug={product.slug}
          name={product.name}
          emoji={product.emoji}
          gradient={product.gradient}
          price={product.price}
        />
      </div>
    </div>
  );
}
