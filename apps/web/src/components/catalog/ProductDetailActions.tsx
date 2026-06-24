"use client";

import { useState } from "react";
import type { Product } from "@/lib/types/catalog";
import { QuantitySelector } from "./QuantitySelector";
import { AddToCartButton } from "./AddToCartButton";

interface ProductDetailActionsProps {
  product: Product;
}

export function ProductDetailActions({ product }: ProductDetailActionsProps) {
  const [quantity, setQuantity] = useState(1);
  const isOutOfStock = product.stock <= 0;

  return (
    <div className="mt-8 space-y-4 border-t border-zinc-100 pt-8">
      <QuantitySelector
        quantity={quantity}
        onChange={setQuantity}
        max={Math.min(product.stock, 99)}
      />
      <AddToCartButton variant="detail" disabled={isOutOfStock} />
    </div>
  );
}
