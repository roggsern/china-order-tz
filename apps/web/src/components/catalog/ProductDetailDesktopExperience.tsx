"use client";

import { useCallback, useState } from "react";
import type { Category, Product } from "@/lib/types/catalog";
import { pickProductShippingContext } from "@/lib/types/catalog";
import { ProductGallery } from "./ProductGallery";
import { ProductDetailPurchasePanel } from "./ProductDetailPurchasePanel";
import { ProductTabs } from "./ProductTabs";

interface ProductDetailDesktopExperienceProps {
  product: Product;
  category?: Category;
}

export function ProductDetailDesktopExperience({
  product,
  category,
}: ProductDetailDesktopExperienceProps) {
  const [selectedColorSlug, setSelectedColorSlug] = useState<string | null>(null);
  const shippingContext = pickProductShippingContext(product);

  const handleSelectedColorChange = useCallback((colorSlug: string | null) => {
    setSelectedColorSlug(colorSlug);
  }, []);

  return (
    <div className="mt-0 hidden lg:mt-6 lg:grid lg:grid-cols-[minmax(0,58fr)_minmax(0,42fr)] lg:items-start lg:gap-x-7">
      <div className="flex min-w-0 flex-col gap-7">
        <ProductGallery
          product={product}
          productName={product.name}
          fallbackEmoji={product.emoji}
          fallbackGradient={product.gradient}
          selectedColorSlug={selectedColorSlug}
        />

        <ProductTabs
          layout="below-gallery"
          description={product.description}
          features={product.features}
          specifications={product.specifications}
          reviews={product.customerReviews}
          reviewCount={product.reviews}
          averageRating={product.rating}
          shippingContext={shippingContext}
          origin={product.origin}
        />
      </div>

      <ProductDetailPurchasePanel
        product={product}
        category={category}
        onSelectedColorChange={handleSelectedColorChange}
      />
    </div>
  );
}
