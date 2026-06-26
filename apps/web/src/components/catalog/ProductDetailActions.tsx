"use client";

import { useState } from "react";
import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import {
  hasSelectableVariants,
  isSizeSelectionRequired,
  normalizeSelectedSize,
  SIZE_REQUIRED_MESSAGE,
} from "@/lib/catalog/variants";
import { QuantitySelector } from "./QuantitySelector";
import { AddToCartButton } from "./AddToCartButton";
import { BuyNowButton } from "./BuyNowButton";
import { WishlistButton } from "./WishlistButton";
import { VariantSelectors } from "./VariantSelectors";

interface ProductDetailActionsProps {
  product: Product;
}

export function ProductDetailActions({ product }: ProductDetailActionsProps) {
  const [quantity, setQuantity] = useState(1);
  const [variant, setVariant] = useState<ProductVariantChoice>({});
  const isOutOfStock = product.stock <= 0;
  const showVariantSelectors = hasSelectableVariants(product);
  const needsSize = isSizeSelectionRequired(product);
  const hasSelectedSize = Boolean(normalizeSelectedSize(variant.size));

  const handleVariantChange = (next: ProductVariantChoice) => {
    setVariant(next);
  };

  return (
    <>
      {showVariantSelectors && (
        <div className="mt-8 border-t border-zinc-100 pt-8">
          <VariantSelectors product={product} variant={variant} onChange={handleVariantChange} />
          {needsSize && !hasSelectedSize && (
            <p className="mt-3 text-sm font-medium text-amber-700" role="status">
              {SIZE_REQUIRED_MESSAGE}
            </p>
          )}
        </div>
      )}

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
            selectedVariant={variant}
          />
          <BuyNowButton disabled={isOutOfStock} />
          <WishlistButton />
        </div>
      </div>
    </>
  );
}
