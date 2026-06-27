"use client";

import { useEffect, useState } from "react";
import { motion } from "framer-motion";
import type { Category, Product, ProductVariantChoice } from "@/lib/types/catalog";
import { pickProductShippingContext } from "@/lib/types/catalog";
import {
  hasSelectableVariants,
  isSizeSelectionRequired,
  normalizeSelectedSize,
  SIZE_REQUIRED_MESSAGE,
} from "@/lib/catalog/variants";
import { trackRecentlyViewed } from "@/lib/catalog/recently-viewed";
import { Breadcrumbs } from "../Breadcrumbs";
import { ProductBadges } from "../ProductBadge";
import { ProductOriginBadge } from "../ProductOriginBadge";
import { TrustBadges } from "../TrustBadges";
import { VariantSelectors } from "../VariantSelectors";
import { QuantitySelector } from "../QuantitySelector";
import { ProductGalleryMobile } from "./ProductGalleryMobile";
import { ProductMobilePrice } from "./ProductMobilePrice";
import { ProductMobileQuickInfo } from "./ProductMobileQuickInfo";
import { ProductMobileTabs } from "./ProductMobileTabs";
import { ProductSupplierCard } from "./ProductSupplierCard";
import { ProductHorizontalScroll } from "./ProductHorizontalScroll";
import { RecentlyViewedProducts } from "./RecentlyViewedProducts";
import { ProductMobileStickyBar } from "./ProductMobileStickyBar";

interface ProductDetailMobileProps {
  product: Product;
  category: Category | undefined;
  relatedProducts: Product[];
}

export function ProductDetailMobile({
  product,
  category,
  relatedProducts,
}: ProductDetailMobileProps) {
  const [quantity, setQuantity] = useState(1);
  const [variant, setVariant] = useState<ProductVariantChoice>({});
  const isOutOfStock = product.stock <= 0;
  const showVariantSelectors = hasSelectableVariants(product);
  const needsSize = isSizeSelectionRequired(product);
  const hasSelectedSize = Boolean(normalizeSelectedSize(variant.size));
  const shippingContext = pickProductShippingContext(product);

  useEffect(() => {
    trackRecentlyViewed(product);
  }, [product]);

  return (
    <div className="pb-32 lg:hidden">
      <div className="px-4 pt-3">
        <Breadcrumbs
          items={[
            { label: "Products", href: "/products" },
            ...(category
              ? [
                  { label: category.name, href: `/categories/${category.slug}` },
                  { label: product.name },
                ]
              : [{ label: product.name }]),
          ]}
        />
      </div>

      <div className="mt-2">
        <ProductGalleryMobile product={product} />
      </div>

      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.32 }}
        className="space-y-4 px-4 pt-4"
      >
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <ProductBadges badges={product.badges} />
            {category && (
              <span className="rounded-full bg-zinc-100 px-2.5 py-1 text-[11px] font-medium text-zinc-600">
                {category.icon} {category.name}
              </span>
            )}
          </div>

          {product.brand && (
            <p className="mt-2.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c9a227]">
              {product.brand}
            </p>
          )}

          <h1 className="mt-1 text-[1.35rem] font-bold leading-snug tracking-tight text-zinc-900">
            {product.name}
          </h1>
        </div>

        <ProductMobileQuickInfo
          rating={product.rating}
          reviewCount={product.reviews}
          stock={product.stock}
          origin={product.origin}
          shippingContext={shippingContext}
        />

        <ProductMobilePrice price={product.price} oldPrice={product.oldPrice} />

        <div className="flex flex-wrap items-center gap-2">
          <ProductOriginBadge origin={product.origin} size="md" />
          {product.trustBadges.length > 0 && (
            <TrustBadges badges={product.trustBadges.slice(0, 2)} size="sm" />
          )}
        </div>

        <div className="rounded-2xl border border-zinc-100 bg-white p-4 shadow-sm">
          <QuantitySelector
            quantity={quantity}
            onChange={setQuantity}
            max={Math.min(product.stock, 99)}
            variant="mobile"
          />

          {showVariantSelectors && (
            <div className="mt-4 border-t border-zinc-100 pt-4">
              <VariantSelectors product={product} variant={variant} onChange={setVariant} />
              {needsSize && !hasSelectedSize && (
                <p className="mt-2.5 text-sm font-medium text-amber-700" role="status">
                  {SIZE_REQUIRED_MESSAGE}
                </p>
              )}
            </div>
          )}
        </div>

        <ProductMobileTabs
          description={product.description}
          features={product.features}
          specifications={product.specifications}
          reviews={product.customerReviews}
          reviewCount={product.reviews}
          averageRating={product.rating}
          shippingContext={shippingContext}
          origin={product.origin}
        />

        <ProductSupplierCard
          origin={product.origin}
          brand={product.brand}
          trustBadges={product.trustBadges}
          rating={product.rating}
          reviewCount={product.reviews}
        />
      </motion.div>

      <div className="mt-6 space-y-6">
        <RecentlyViewedProducts currentProductId={product.id} />

        {relatedProducts.length > 0 && (
          <ProductHorizontalScroll
            title="Related Products"
            subtitle="You may also like"
            products={relatedProducts}
          />
        )}
      </div>

      <ProductMobileStickyBar
        product={product}
        quantity={quantity}
        selectedVariant={variant}
        disabled={isOutOfStock}
      />
    </div>
  );
}
